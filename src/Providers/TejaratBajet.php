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
 * Tejarat Bajet (credit/BNPL with OTP) — ported from farayaz/larapay.
 *
 * This gateway is an OTP-based credit flow and maps loosely onto the generic
 * interface:
 *  - the customer national id is read from config ('national_id'),
 *  - the OTP entered by the customer is passed to verify() as $cardNumber,
 *  - pay() cannot render the gateway's OTP page directly (that is the host
 *    app's responsibility); it redirects to the configured callback.
 */
class TejaratBajet extends Base implements IPaymentChannel
{
    private string $baseUrl = 'https://smq.stts.ir/';

    private array $statuses = [
        'TrackerAlreadyUsed' => 'کد پیگیری تکراری',
    ];

    /** @var array<string,array{token:string,expires:int}> */
    private static array $tokenCache = [];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'TejaratBajet';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.tejarat_bajet.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $nationalId = (string) $this->cfg('national_id', '');

        $balance = $this->request('get', 'customers/' . $nationalId . '/balance');
        if (($balance['result']['balance'] ?? 0) < $amount) {
            throw new GatewayException('باجت: عدم موجودی کافی. موجودی: ' . number_format($balance['result']['balance'] ?? 0) . ' ریال');
        }

        $this->request('post', 'customers/' . $nationalId . '/purchases/authorization?trackId=' . $trackingCode, [
            'amount' => $amount,
            'description' => $description ?? $trackingCode,
        ]);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $balance;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $trackingCode;
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
        return $this->callback;
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $nationalId = (string) $this->cfg('national_id', '');
        $trackId = $trackingCode ?? $paymentToken;

        // $cardNumber carries the OTP entered by the customer.
        $this->request('post', 'customers/' . $nationalId . '/purchases?trackId=' . $trackId, [
            'otp' => $cardNumber,
            'amount' => $amount,
            'description' => $trackId,
        ]);

        $result = $this->request('post', 'customers/' . $nationalId . '/purchases/advice?trackId=' . $trackId);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->providerMessage = $result['message'] ?? null;
        $paymentResponse->trackingCode = $result['result']['referenceNumber'] ?? null;
        $paymentResponse->referenceCode = $result['result']['referenceNumber'] ?? null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['trackId'] ?? null;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function authenticate(): string
    {
        $cache = self::$tokenCache[static::class] ?? null;
        if ($cache && $cache['expires'] > time()) {
            return $cache['token'];
        }

        $result = $this->request('post', 'token', [
            'client_id' => $this->cfg('client_id'),
            'client_secret' => $this->cfg('client_secret'),
            'grant_type' => 'password',
            'username' => $this->cfg('username'),
            'password' => $this->cfg('password'),
        ]);
        self::$tokenCache[static::class] = [
            'token' => $result['access_token'],
            'expires' => time() + (int) $result['expires_in'] - 10,
        ];

        return $result['access_token'];
    }

    private function request(string $method, string $url, array $data = [], array $headers = [], int $timeout = 10): array
    {
        $client = Http::timeout($timeout);
        $fullUrl = $this->baseUrl;
        if ($url != 'token') {
            $fullUrl .= 'facilitycustomer/api/v1/';
            $headers['Authorization'] = 'Bearer ' . $this->authenticate();
        } else {
            $client = $client->asForm();
        }
        $fullUrl .= $url;

        return $client
            ->acceptJson()
            ->withHeaders($headers)
            ->{$method}($fullUrl, $data)
            ->throw()
            ->json();
    }
}
