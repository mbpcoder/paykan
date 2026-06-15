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
 * Digipay (mydigipay.com) — ported from farayaz/larapay. REST/JSON, GET redirect.
 */
class Digipay extends Base implements IPaymentChannel
{
    private string $url = 'https://api.mydigipay.com/digipay/api/';

    private array $statuses = [
        'id-mismatch' => 'عدم تطبیق شناسه برگشتی',
        '401-authenticate' => 'اطلاعات ورود اشتباه است',
    ];

    private static ?string $cachedToken = null;
    private static int $cachedTokenExpiresAt = 0;

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Digipay';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.digipay.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $url = $this->url . 'businesses/ticket?type=0';
        $data = [
            'amount' => $amount,
            'cellNumber' => null,
            'providerId' => $trackingCode,
            'redirectUrl' => $this->callback,
            'userType' => 2,
        ];
        $headers = [
            'Authorization' => 'Bearer ' . $this->authenticate(),
        ];
        $result = $this->_request('post', $url, $data, $headers);

        if (($result['result']['status'] ?? -1) != 0) {
            $message = $result['result']['message'] ?? 'unknown error';
            throw new GatewayException($message);
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['ticket'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $this->payUrl($result['ticket']);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->payUrl($paymentToken));
    }

    public function payUrl(string|int $paymentToken): string
    {
        return $this->url . 'purchases/ipg/pay/' . $paymentToken;
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $url = $this->url . 'purchases/verify/' . $paymentToken;
        $headers = [
            'Authorization' => 'Bearer ' . $this->authenticate(),
        ];
        $result = $this->_request('post', $url, [], $headers);

        if (($result['result']['status'] ?? -1) != 0) {
            $message = $result['result']['message'] ?? 'unknown error';
            throw new GatewayException($message);
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber ?? ($result['maskedPan'] ?? null);
        $paymentResponse->trackingCode = isset($result['trackingCode']) ? (string) $result['trackingCode'] : null;
        $paymentResponse->referenceCode = isset($result['rrn']) ? (string) $result['rrn'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $default = [
            'amount' => null,
            'providerId' => null,
            'trackingCode' => null,
            'rrn' => null,
            'pspName' => null,
            'redirectUrl' => null,
            'fundProviderCode' => null,
            'resultStatus' => null,
            'type' => null,
        ];
        $params = array_merge($default, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['trackingCode'] ?? null;
        $paymentResponse->referenceCode = $params['rrn'] ?? null;
        $paymentResponse->trackingCode = $params['trackingCode'] ?? null;
        $paymentResponse->paymentStatus = ($params['providerId'] !== null)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function authenticate()
    {
        if (self::$cachedToken !== null && self::$cachedTokenExpiresAt > time()) {
            return self::$cachedToken;
        }

        $url = $this->url . 'oauth/token';
        $data = [
            'username' => $this->cfg('username'),
            'password' => $this->cfg('password'),
            'grant_type' => 'password',
        ];
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->cfg('client_id') . ':' . $this->cfg('client_secret')),
        ];

        $result = $this->_request('post', $url, $data, $headers);

        self::$cachedToken = $result['access_token'];
        self::$cachedTokenExpiresAt = time() + ($result['expires_in'] - 10);

        return $result['access_token'];
    }

    private function _request(string $method, string $url, array $data = [], array $headers = [], $timeout = 10)
    {
        return Http::timeout($timeout)
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw()
            ->json();
    }
}
