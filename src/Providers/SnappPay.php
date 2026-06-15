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
 * SnappPay (https://snapppay.ir/) — ported from farayaz/larapay. REST/JSON, GET redirect.
 *
 * The OAuth access token is cached in-process (static) for the lifetime of the
 * request, replacing larapay's Laravel Cache usage.
 */
class SnappPay extends Base implements IPaymentChannel
{
    private string $url = 'https://api.snapppay.ir/';

    private array $statuses = [
        'FAILED' => 'پرداخت ناموفق',
        'not-eligible' => 'not-eligible: قابل پرداخت با اسنپ‌پی نیست',
    ];

    /** @var array{token:string,expires_at:int}|null */
    private static ?array $accessToken = null;

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'SnappPay';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.snapp_pay.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $result = $this->request('get', 'api/online/offer/v1/eligible', ['amount' => $amount]);
        if (! ($result['response']['eligible'] ?? false)) {
            throw new GatewayException($this->translateStatus('not-eligible'));
        }

        $mobile = $this->cfg('mobile', '');
        $data = [
            'transactionId' => $trackingCode,
            'amount' => $amount,
            'returnURL' => $this->callback,
            'paymentMethodTypeDto' => 'INSTALLMENT',
            'mobile' => '+98' . (int) $mobile,
            'externalSourceAmount' => 0,
            'discountAmount' => 0,
            'cartList' => [],
        ];
        $result = $this->request('post', 'api/online/payment/v1/token', $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['response']['paymentToken'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->payUrl($paymentToken));
    }

    public function payUrl(string|int $paymentToken): string
    {
        $extra = '&venture=8q169w5lz1xkpom0gj2d&vtr=JDJhJDEwJFpjMGs3R1BVQ1A2TVUxUlVEUVBPYS5JVGlLRVZaTDNiVS8zMzE0akxHRURDSGlNTmxnWnpL';

        return 'https://payment.snapppay.ir/?paymentToken=' . $paymentToken . $extra;
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'paymentToken' => $paymentToken,
        ];
        $this->request('post', 'api/online/payment/v1/verify', $data);
        $result = $this->request('post', 'api/online/payment/v1/settle', $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->referenceCode = isset($result['response']['transactionId']) ? (string) $result['response']['transactionId'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['paymentToken'] ?? null;
        $paymentResponse->trackingCode = isset($params['transactionId']) ? (string) $params['transactionId'] : null;
        $paymentResponse->paymentStatus = (($params['state'] ?? null) == 'OK')
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function authenticate(): string
    {
        if (self::$accessToken !== null && self::$accessToken['expires_at'] > time()) {
            return self::$accessToken['token'];
        }

        $data = [
            'username' => $this->cfg('username'),
            'password' => $this->cfg('password'),
            'grant_type' => 'password',
            'scope' => 'online-merchant',
        ];
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($this->cfg('client_id') . ':' . $this->cfg('client_secret')),
        ];
        $result = $this->request('post', 'api/online/v1/oauth/token', $data, $headers);
        self::$accessToken = [
            'token' => $result['access_token'],
            'expires_at' => time() + ((int) $result['expires_in'] - 10),
        ];

        return $result['access_token'];
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }

    private function request(string $method, string $url, array $data = [], array $headers = []): array
    {
        $http = Http::timeout(10);
        if ($url == 'api/online/v1/oauth/token') {
            $http = $http->asForm();
        } else {
            $http = $http->acceptJson();
            $headers['Authorization'] = 'Bearer ' . $this->authenticate();
        }

        return $http
            ->withHeaders($headers)
            ->{$method}($this->url . $url, $data)
            ->throw()
            ->json();
    }
}
