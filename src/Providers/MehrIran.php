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
 * Mehr Iran (Resalat — kalayeiranipg.qmb.ir) — ported from farayaz/larapay.
 * Morilog\Jalali is replaced with a small inline Jalali converter.
 */
class MehrIran extends Base implements IPaymentChannel
{
    private string $url = 'https://kalayeiranipg.qmb.ir/pg/';

    private array $statuses = [
        'id-mismatch' => 'عدم تطبیق شناسه بازگشتی',
        'token-mismatch' => 'عدم تطبیق توکن',
        '03' => 'طرح اقساطی پذیرنده با کارت منطبق نیست.',
        '06' => 'بروز خطای سیستمی',
        '12' => 'تراکنش نامعتبر است.',
        '25' => 'عدم وجود اطلاعات مورد نظر جهت به روز آوری یا انجام عملیات.',
        '31' => 'جدول آزادسازی پذیرنده تعریف نشده است.',
        '33' => 'تاریخ انقضا کارت سپری شده',
        '39' => 'کارت حساب اعتباری ندارد.',
        '41' => 'کارت مفقودی می باشد.',
        '51' => 'موجودی کافی نمی باشد.',
        '54' => 'تاریخ انقضا کارت سپری شده است.',
        '55' => 'رمز کارت وارد شده اشتباه می باشد.',
        '60' => 'پذیرنده غیرفعال می باشد.',
        '75' => 'تعداد دفعات ورود رمز بیش از حد مجاز است.',
        '84' => 'وضعیت سامانه یا بانک غیرفعال می باشد.',
        '96' => 'بروز خطای سیستمی در انجام تراکنش.',
        '9102' => 'مبلغ تراکنش بیشتر یا کمتر از حد مجاز می باشد.',
        '9105' => 'تقسیم وجه برای پذیرنده غیرفعال است.',
        '9201' => 'مشتری از پرداخت انصراف داده است.',
        '9214' => 'شماره تراکنش معتبر نمی باشد.',
        '9215' => 'چرخه تراکنش نقض شده است.',
        '9217' => 'تراکنش دارای مغایرت می باشد.',
        '9219' => 'زمان انجام تراکنش به پایان رسیده و لغو شده است.',
        '9220' => 'تراکنش قبلا لغو شده است.',
        '9221' => 'تراکنش قبلا با موفقیت انجام شده',
        '9222' => 'دسترسی هم زمان به تراکنش',
        '9223' => 'خطای غیر منتظره',
        '9224' => 'قبض قبلا پرداخت شده است',
        '9301' => 'درخواست با امضای دیجیتال مطابقت ندارد',
        '9302' => 'دسترسی غیر مجاز',
        '9501' => 'از شبکه پرداخت پاسخی دریافت نشد',
        '9601' => 'پارامترهای ورودی اشتباه می باشد',
        '9701' => 'ترمینال در سیستم وجود ندارد',
        '9702' => 'کد ملی پذیرنده در سیستم وجود ندارد',
        '9703' => 'کد ملی مشتری در سیستم وجود ندارد',
        '9704' => 'شماره موبایل مشتری در سیستم وجود ندارد',
        '9705' => 'ارسال رمز با خطا مواجه شد',
        '9706' => 'شماره کارت مشتری در سیستم وجود ندارد.',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'MehrIran';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.mehr_iran.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'terminal-id' => $this->cfg('terminal_id'),
            'merchant-nid' => $this->cfg('merchant_nid'),
            'order-id' => $trackingCode,
            'revert-url' => $this->callback,
            'trxtype' => 'sale',
            'amount' => $amount,
            'date' => $this->jalaliDateTime(),
        ];
        $params['sign'] = $this->sign($params);

        $result = $this->request('post', $this->url . 'service/vpos/trxReq', $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['transaction-id'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), [
            'transaction_id' => $paymentToken,
            'sign' => $this->sign([$paymentToken]),
        ]);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $this->url . 'pay';
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'transaction-id' => $paymentToken,
            'operation' => 'confirm',
        ];
        $data['sign'] = $this->sign($data);

        $result = $this->request('post', $this->url . 'service/vpos/trxConfirm', $data);
        if (($result['transaction-id'] ?? null) != $paymentToken) {
            throw new GatewayException($this->translateStatus('token-mismatch'));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['transaction-id'] ?? null;
        $paymentResponse->providerCode = $params['resp-code'] ?? null;
        $paymentResponse->trackingCode = $params['trace'] ?? null;
        $paymentResponse->referenceCode = $params['rrn'] ?? null;
        $paymentResponse->paymentStatus = (($params['resp-code'] ?? null) === '00')
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
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

        if (($result['resp-code'] ?? null) != '00') {
            throw new GatewayException($this->translateStatus($result['resp-code'] ?? null));
        }

        return $result;
    }

    private function sign(array $params): string
    {
        return hash_hmac('sha256', implode('*', $params), hex2bin($this->cfg('encrypt_key')));
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }

    private function jalaliDateTime(): string
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

        return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, date('H:i'));
    }
}
