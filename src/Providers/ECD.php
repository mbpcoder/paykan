<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Exceptions\GatewayException;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\FormRedirect;
use MbpCoder\Payment\Support\Http\Http;

/**
 * ECD (ecd.shaparak.ir) — ported from farayaz/larapay. REST + POST-form redirect.
 */
class ECD extends Base implements IPaymentChannel
{
    private string $url = 'https://ecd.shaparak.ir/ipg_ecd/';

    private array $statuses = [];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'ECD';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.ecd.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $data = [
            'TerminalNumber' => $this->cfg('terminal_number'),
            'BuyID' => $trackingCode,
            'Amount' => $amount,
            'Date' => date('Y/m/d'),
            'Time' => date('H:m'),
            'RedirectURL' => $this->callback,
        ];
        $data['CheckSum'] = sha1(implode('', array_values($data)) . $this->cfg('hash_key'));
        $data['Language'] = 'fa';

        $result = $this->_request('PayRequest', $data);
        if (! empty($result['ErrorCode'])) {
            throw new GatewayException($this->translateStatus($result['ErrorDescription']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['Res'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), ['Token' => $paymentToken]);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $this->url . 'PayStart';
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'Token' => $paymentToken,
        ];
        $result = $this->_request('PayConfirmation', $data);
        if (! empty($result['ErrorCode'])) {
            throw new GatewayException($this->translateStatus($result['ErrorDescription']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = isset($result['TrackingNumber']) ? (string) $result['TrackingNumber'] : ($trackingCode !== null ? (string) $trackingCode : null);
        $paymentResponse->referenceCode = isset($result['ReferenceNumber']) ? (string) $result['ReferenceNumber'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $default = [
            'State' => null,
            'Amount' => null,
            'ErrorCode' => null,
            'ErrorDescription' => null,
            'ReferenceNumber' => null,
            'TrackingNumber' => null,
            'BuyID' => null,
            'Token' => null,
        ];
        $params = array_merge($default, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['Token'] ?? null;
        $paymentResponse->referenceCode = $params['ReferenceNumber'] ?? null;
        $paymentResponse->trackingCode = $params['TrackingNumber'] ?? null;
        $paymentResponse->paymentStatus = empty($params['ErrorCode'])
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function _request(string $path, array $data = [])
    {
        $url = $this->url . $path;

        return Http::timeout(10)
            ->withoutVerifying()
            ->post($url, $data)
            ->throw()
            ->json();
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
