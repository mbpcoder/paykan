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
 * Torobpay (cpg.torobpay.com) — ported from shetabit/multipay.
 */
class TorobPay extends Base implements IPaymentChannel
{
    private const OAUTH_URL = '/api/online/v1/oauth/token';
    private const PURCHASE_URL = '/api/online/payment/v1/token';
    private const VERIFY_URL = '/api/online/payment/v1/verify';

    private static ?string $cachedToken = null;

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'TorobPay';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.torob_pay.' . $key, $default);
    }

    private function apiUrl(string $path): string
    {
        return rtrim($this->cfg('api_url'), '/') . $path;
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $payload = [
            'amount' => $amount,
            'paymentMethodTypeDto' => 'ONLINE_CREDIT',
            'returnURL' => $this->callback,
            'transactionId' => (string) $trackingCode,
            'cartList' => [
                [
                    'cartId' => (string) $trackingCode,
                    'totalAmount' => $amount,
                    'tax_amount' => 0,
                    'shipping_amount' => 0,
                    'is_tax_included' => false,
                    'is_shipment_included' => false,
                    'cartItems' => [],
                ],
            ],
        ];

        $result = $this->_request('post', $this->apiUrl(self::PURCHASE_URL), $payload);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['response']['paymentToken'];
        $paymentResponse->paymentUrl = $result['response']['paymentPageUrl'];
        $paymentResponse->wage = 0;
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
        return $this->apiUrl('/api/online/payment/v1/pay') . '?paymentToken=' . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $result = $this->_request('post', $this->apiUrl(self::VERIFY_URL), [
            'paymentToken' => $paymentToken,
        ]);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->referenceCode = $result['response']['transactionId'] ?? null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = ($result['successful'] ?? false)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['paymentToken'] ?? null;
        $paymentResponse->trackingCode = $params['transactionId'] ?? null;
        $paymentResponse->paymentStatus = isset($params['paymentToken'])
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function authenticate(): string
    {
        if (self::$cachedToken !== null) {
            return self::$cachedToken;
        }

        $result = Http::withHeader(
            'Authorization',
            'Basic ' . base64_encode($this->cfg('client_id') . ':' . $this->cfg('client_secret'))
        )
            ->asForm()
            ->post($this->apiUrl(self::OAUTH_URL), [
                'username' => $this->cfg('username'),
                'password' => $this->cfg('password'),
                'grant_type' => 'password',
            ])
            ->throw()
            ->json();

        return self::$cachedToken = $result['access_token'];
    }

    private function _request(string $method, string $url, array $data = []): array
    {
        $result = Http::withToken($this->authenticate())
            ->acceptJson()
            ->{$method}($url, $data)
            ->throw()
            ->json();

        if (!($result['successful'] ?? true)) {
            $message = $result['error']['message'] ?? $result['result']['message'] ?? 'unknown error';
            throw new GatewayException($message);
        }

        return $result;
    }
}
