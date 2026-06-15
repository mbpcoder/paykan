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
 * RefahBeta (Refah BNPL, https://api.rb24.ir/) — ported from farayaz/larapay.
 *
 * Verify flow adaptation: $trackingCode carries the original order id used to
 * build the installment title; the OTP entered by the user on the redirect
 * form is delivered back through processCallback / verify $params.
 *
 * The larapay original computed `startDate` with Morilog\Jalali. Since this
 * package has no Jalali dependency, a small self-contained Gregorian->Jalali
 * converter reproduces the same "end of (current or next) Jalali month"
 * semantics.
 */
class RefahBeta extends Base implements IPaymentChannel
{
    private string $url = 'https://api.rb24.ir/';

    private array $statuses = [
        'invalid_client' => 'invalid_client: خطای سرویس گیرنده',
        'not-enough-credit' => 'اعتبار کافی نیست، اعتبار ماهانه: ',
        'rial' => ' ریال',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'RefahBeta';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.refah_beta.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $nationalId = (string) $this->cfg('national_id', '');

        $url = 'beta/1.0/credit/' . $nationalId . '/inquiry';
        $result = $this->request('post', $url);
        if ($result['status'] != 200) {
            throw new GatewayException($result['message']);
        }
        if ($amount / $this->cfg('number_of_installments') > $result['data']['credit']) {
            $message = $this->translateStatus('not-enough-credit');
            $message .= number_format($result['data']['credit']);
            $message .= $this->translateStatus('rial');
            throw new GatewayException($message);
        }

        $url = 'beta/1.0/credit/' . $nationalId . '/request';
        $data = [
            'title' => 'transaction' . $trackingCode,
            'startDate' => $this->startDate(),
            'amount' => $amount,
            'numberOfInstallments' => $this->cfg('number_of_installments'),
        ];
        $result = $this->request('post', $url, $data);
        if ($result['status'] != 200) {
            throw new GatewayException($result['message']);
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $trackingCode;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = null;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), ['otp' => '']);
    }

    public function payUrl(string|int $paymentToken): string
    {
        return $this->callback;
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null, array $params = []): PaymentResponse
    {
        $nationalId = (string) $this->cfg('national_id', '');
        $otp = $params['otp'] ?? null;

        $url = 'beta/1.0/credit/' . $nationalId . '/consume';
        $data = [
            'otp' => $otp,
            'title' => 'transaction' . $trackingCode,
            'startDate' => $this->startDate(),
            'amount' => $amount,
            'numberOfInstallments' => $this->cfg('number_of_installments'),
            'requestId' => (string) $trackingCode,
        ];
        $result = $this->request('post', $url, $data);
        if ($result['status'] != 200) {
            throw new GatewayException($result['message']);
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = isset($result['data']) ? (string) $result['data'] : null;
        $paymentResponse->referenceCode = isset($result['data']) ? (string) $result['data'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = isset($params['otp']) ? (string) $params['otp'] : null;
        $paymentResponse->paymentStatus = isset($params['otp'])
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
        $data = [
            'client_id' => $this->cfg('client_id'),
            'client_secret' => $this->cfg('client_secret'),
            'grant_type' => 'client_credentials',
        ];
        $result = $this->request('post', 'connect/token', $data);

        return $result['access_token'];
    }

    private function request(string $method, string $url, array $data = [], array $headers = [], int $timeout = 10): array
    {
        $fullUrl = $this->url . $url;
        $isForm = ($url == 'connect/token');

        if (! $isForm) {
            $headers['Authorization'] = 'Bearer ' . $this->authenticate();
            $headers['apikey'] = $this->cfg('api_key');
        }

        $http = Http::timeout($timeout);
        $http = $isForm ? $http->asForm() : $http->acceptJson();

        return $http
            ->withHeaders($headers)
            ->{$method}($fullUrl, $data)
            ->throw()
            ->json();
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }

    /**
     * End of the current Jalali month (or the next one if today is past the
     * 15th) as an ISO-8601 timestamp, mirroring the larapay Jalalian logic.
     */
    private function startDate(): string
    {
        [$jy, $jm, $jd] = $this->gregorianToJalali((int) date('Y'), (int) date('n'), (int) date('j'));
        if ($jd > 15) {
            $jm++;
            if ($jm > 12) {
                $jm = 1;
                $jy++;
            }
        }
        $lastDay = $this->jalaliMonthLength($jy, $jm);
        [$gy, $gm, $gd] = $this->jalaliToGregorian($jy, $jm, $lastDay);

        return (new \DateTime(sprintf('%04d-%02d-%02d 00:00:00', $gy, $gm, $gd)))->format(\DateTime::ATOM);
    }

    private function jalaliMonthLength(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }
        // Esfand: 30 in leap years, otherwise 29.
        return $this->isJalaliLeap($jy) ? 30 : 29;
    }

    private function isJalaliLeap(int $jy): bool
    {
        $mod = $jy % 33;

        return in_array($mod, [1, 5, 9, 13, 17, 22, 26, 30], true);
    }

    private function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int) (($gy2 + 3) / 4)) - ((int) (($gy2 + 99) / 100))
            + ((int) (($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * ((int) ($days / 12053)));
        $days %= 12053;
        $jy += 4 * ((int) ($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int) ($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int) (($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    private function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (((int) ($jy / 33)) * 8) + ((int) ((($jy % 33) + 3) / 4))
            + $jd + (($jm < 7) ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186));
        $gy = 400 * ((int) ($days / 146097));
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * ((int) (--$days / 36524));
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * ((int) ($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $gy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $sal_a = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28,
            31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;
        while ($gm < 13 && $gd > $sal_a[$gm]) {
            $gd -= $sal_a[$gm];
            $gm++;
        }

        return [$gy, $gm, $gd];
    }
}
