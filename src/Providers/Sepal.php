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
 * Sepal (https://sepal.ir/) — ported from farayaz/larapay.
 */
class Sepal extends Base implements IPaymentChannel
{
    private string $url = 'https://sepal.ir/';

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Sepal';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.sepal.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'apiKey' => $this->cfg('api_key'),
            'amount' => $amount,
            'callbackUrl' => $this->callback,
            'invoiceNumber' => $trackingCode,
            'payerMobile' => $this->cfg('mobile', ''),
            'description' => $description ?? $trackingCode,
        ];
        $result = $this->request('post', $this->url . 'api/request.json', $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['paymentNumber'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $this->url . 'payment/' . $result['paymentNumber'];
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->payUrl($paymentToken));
    }

    public function payUrl(string|int $paymentToken): string
    {
        return $this->url . 'payment/' . $paymentToken;
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'apiKey' => $this->cfg('api_key'),
            'paymentNumber' => $paymentToken,
            'invoiceNumber' => $trackingCode,
        ];
        $result = $this->request('post', $this->url . 'api/verify.json', $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $result['cardNumber'] ?? null;
        $paymentResponse->trackingCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->referenceCode = (string) $paymentToken;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['paymentNumber'] ?? null;
        $paymentResponse->trackingCode = isset($params['invoiceNumber']) ? (string) $params['invoiceNumber'] : null;
        $paymentResponse->paymentStatus = (($params['status'] ?? null) == 1)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function request(string $method, string $url, array $data = [], array $headers = [], int $timeout = 10): array
    {
        $result = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw()
            ->json();

        if (($result['status'] ?? null) != 1) {
            throw new GatewayException($result['message'] ?? 'unknown error');
        }

        return $result;
    }
}
