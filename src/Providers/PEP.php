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
 * Parsian (PEP — pep.shaparak.ir) — ported from farayaz/larapay.
 *
 * The Laravel Cache used by larapay for token caching is replaced with a
 * static in-memory cache, and Morilog\Jalali with a small inline converter.
 */
class PEP extends Base implements IPaymentChannel
{
    private string $url = 'https://pep.shaparak.ir/dorsa1';

    /** @var array<string,array{token:string,expires:int}> */
    private static array $tokenCache = [];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'PEP';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.pep.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $data = [
            'amount' => $amount,
            'invoice' => $trackingCode,
            'invoiceDate' => $this->jalaliDate(),
            'serviceCode' => 8,
            'serviceType' => 'PURCHASE',
            'callbackApi' => $this->callback,
            'payerMail' => '',
            'payerName' => '',
            'mobileNumber' => $this->cfg('mobile', ''),
            'terminalNumber' => $this->cfg('terminal_number'),
            'description' => $description ?? $trackingCode,
            'pans' => '',
            'nationalCode' => $this->cfg('national_id', ''),
        ];
        $result = $this->request('post', 'api/payment/purchase', $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['data']['urlId'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $this->url . $result['data']['urlId'];
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
        return $this->url . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'invoice' => $trackingCode,
            'urlId' => $paymentToken,
        ];
        $result = $this->request('post', 'api/payment/confirm-transactions', $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $result['data']['maskedCardNumber'] ?? null;
        $paymentResponse->trackingCode = isset($result['data']['trackId']) ? (string) $result['data']['trackId'] : null;
        $paymentResponse->referenceCode = isset($result['data']['referenceNumber']) ? (string) $result['data']['referenceNumber'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['trackId'] ?? null;
        $paymentResponse->referenceCode = $params['referenceNumber'] ?? null;
        $paymentResponse->trackingCode = isset($params['invoiceId']) ? (string) $params['invoiceId'] : null;
        $paymentResponse->paymentStatus = (($params['status'] ?? null) === 'success')
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
        $cache = self::$tokenCache[static::class] ?? null;
        if ($cache && $cache['expires'] > time()) {
            return $cache['token'];
        }

        $data = [
            'username' => $this->cfg('username'),
            'password' => $this->cfg('password'),
        ];
        $result = $this->request('post', 'token/getToken', $data);

        self::$tokenCache[static::class] = ['token' => $result['token'], 'expires' => time() + 300];

        return $result['token'];
    }

    private function request(string $method, string $url, array $data = [], array $headers = []): array
    {
        if ($url != 'token/getToken') {
            $headers['Authorization'] = 'Bearer ' . $this->authenticate();
        }

        $result = Http::timeout(10)
            ->acceptJson()
            ->withHeaders($headers)
            ->{$method}($this->url . $url, $data)
            ->throw()
            ->json();

        if (($result['resultCode'] ?? null) != '0') {
            throw new GatewayException((string) ($result['resultMsg'] ?? 'failed'));
        }

        return $result;
    }

    /**
     * Convert today's Gregorian date to a Jalali "Y-m-d" string.
     */
    private function jalaliDate(): string
    {
        $gy = (int) date('Y');
        $gm = (int) date('n');
        $gd = (int) date('j');

        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return sprintf('%04d-%02d-%02d', $jy, $jm, $jd);
    }
}
