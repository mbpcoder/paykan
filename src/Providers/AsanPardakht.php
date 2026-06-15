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
 * AsanPardakht — ported from farayaz/larapay. REST + POST-form redirect.
 */
class AsanPardakht extends Base implements IPaymentChannel
{
    private array $statuses = [
        'http-401' => 'Unauthorized',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'AsanPardakht';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.asan_pardakht.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'serviceTypeId' => 1,
            'localInvoiceId' => $trackingCode,
            'amountInRials' => $amount,
            'localDate' => date('Ymd His'),
            'callbackURL' => $this->callback,
            'additionalData' => '',
            'paymentId' => 0,
        ];
        $result = $this->_request('post', 'Token', $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = is_string($result) ? $result : (string) $result;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), ['RefId' => $paymentToken]);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return 'https://asan.shaparak.ir';
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'localInvoiceId' => $trackingCode,
        ];
        $result = $this->_request('get', 'TranResult', $data);
        if ($result['payGateTranID'] != $paymentToken) {
            throw new GatewayException($this->translateStatus('token-missmatch'));
        }

        $data = [
            'payGateTranId' => $result['payGateTranId'],
        ];
        $this->_request('post', 'Verify', $data);
        $this->_request('post', 'Settlement', $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber ?? ($result['cardNumber'] ?? null);
        $paymentResponse->trackingCode = isset($result['payGateTranId']) ? (string) $result['payGateTranId'] : ($trackingCode !== null ? (string) $trackingCode : null);
        $paymentResponse->referenceCode = isset($result['rrn']) ? (string) $result['rrn'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $default = [
            'PaygateTranId' => null,
            'MerchantShaparakFee' => null,
            'ReturningParams' => null,
        ];
        $params = array_merge($default, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['PaygateTranId'] ?? null;
        $paymentResponse->referenceCode = $params['PaygateTranId'] ?? null;
        $paymentResponse->paymentStatus = (count(array_filter($params)) == 3)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function _request($method, $url, array $data = [], array $headers = [], $timeout = 10)
    {
        $url = 'https://ipgrest.asanpardakht.ir/v1/' . $url;
        $data['merchantConfigurationId'] = $this->cfg('merchant_configuration_id');
        $headers = [
            'usr' => $this->cfg('username'),
            'pwd' => $this->cfg('password'),
        ];

        $response = Http::timeout($timeout)
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw();

        $decoded = $response->json();

        return $decoded !== null ? $decoded : $response->body();
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
