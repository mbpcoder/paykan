<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Exceptions\GatewayException;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\Http\Http;
use MbpCoder\Payment\Support\Redirect;

/**
 * Vandar IPG (https://ipg.vandar.io/) — ported from farayaz/larapay.
 */
class Vandar extends Base implements IPaymentChannel
{
    private string $url = 'https://ipg.vandar.io/';

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Vandar';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.vandar.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $data = [
            'api_key' => $this->cfg('api_key'),
            'amount' => $amount,
            'callback_url' => $this->callback,
            'mobile_number' => '',
            'factorNumber' => $trackingCode,
            'description' => $description ?? ('transaction:' . $trackingCode),
            'comment' => $trackingCode,
            'valid_card_number' => [],
        ];
        $result = $this->request('post', 'api/v4/send', $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['token'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $this->url . 'v4/' . $result['token'];
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->payUrl($paymentToken));
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $this->url . 'v4/' . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'api_key' => $this->cfg('api_key'),
            'token' => $paymentToken,
        ];
        $result = $this->request('post', 'api/v4/verify', $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $result['cardNumber'] ?? null;
        $paymentResponse->trackingCode = isset($result['transId']) ? (string) $result['transId'] : null;
        $paymentResponse->referenceCode = isset($result['transId']) ? (string) $result['transId'] : null;
        $paymentResponse->wage = ($result['wage'] ?? 0) + ($result['shaparakWage'] ?? 0);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['token'] ?? null;
        $paymentResponse->paymentStatus = (($params['payment_status'] ?? null) === 'OK')
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        $result = Http::timeout(10)
            ->acceptJson()
            ->withHeaders($headers)
            ->{$method}($this->url . $path, $data)
            ->json();

        if (($result['status'] ?? null) != '1') {
            throw new GatewayException(implode(', ', $result['errors'] ?? ['failed']));
        }

        return $result;
    }
}
