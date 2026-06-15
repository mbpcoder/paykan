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
 * Shepa (https://shepa.com/) — ported from farayaz/larapay. REST/JSON, GET redirect.
 */
class Shepa extends Base implements IPaymentChannel
{
    private array $statuses = [
        'failed' => 'ناموفق',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Shepa';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.shepa.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $url = $this->endpoint('api/v1/token');
        $params = [
            'api' => $this->cfg('api'),
            'amount' => $amount,
            'callback' => $this->callback,
            'mobile' => $this->cfg('mobile', ''),
            'email' => '',
            'cardnumber' => '',
            'description' => $trackingCode,
        ];
        $result = $this->request('post', $url, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['result']['token'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $this->endpoint('v1/' . $result['result']['token']);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->payUrl($paymentToken));
    }

    public function payUrl(string|int $paymentToken): string
    {
        return $this->endpoint('v1/' . $paymentToken);
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $url = $this->endpoint('api/v1/verify');
        $data = [
            'token' => $paymentToken,
            'amount' => $amount,
            'api' => $this->cfg('api'),
        ];
        $result = $this->request('post', $url, $data);
        if ($amount != $result['result']['amount']) {
            throw new GatewayException($this->translateStatus('amount-mismatch'));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $result['result']['card_pan'] ?? null;
        $paymentResponse->trackingCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->referenceCode = isset($result['result']['refid']) ? (string) $result['result']['refid'] : null;
        $paymentResponse->bankTrackingCode = isset($result['result']['transaction_id']) ? (string) $result['result']['transaction_id'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['token'] ?? null;
        $paymentResponse->paymentStatus = (($params['status'] ?? null) == 'success')
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function translateStatus(int|string|null $code): string
    {
        $statuses = $this->statuses + [
            'amount-mismatch' => 'عدم تطبیق مبلغ بازگشتی',
            'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
        ];

        return $statuses[$code] ?? ((string) ($code ?? 'failed'));
    }

    private function endpoint(string $path): string
    {
        $url = 'https://merchant.shepa.com/';
        if ($this->cfg('api') == 'sandbox') {
            $url = 'https://sandbox.shepa.com/';
        }

        return $url . $path;
    }

    private function request(string $method, string $url, array $data = [], array $headers = []): array
    {
        $result = Http::timeout(10)
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw()
            ->json();

        if (! ($result['success'] ?? false)) {
            throw new GatewayException($this->translateStatus(implode(', ', $result['error'] ?? ['failed'])));
        }
        if (! empty($result['errors'])) {
            throw new GatewayException($this->translateStatus(($result['message'] ?? null) ?: implode(', ', $result['errors'])));
        }

        return $result;
    }
}
