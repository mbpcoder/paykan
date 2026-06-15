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
 * TabaPay (https://tabapay.ir/) — ported from farayaz/larapay. REST/JSON, GET redirect.
 */
class TabaPay extends Base implements IPaymentChannel
{
    private string $url = 'https://api.tabapay.ir/v1/';

    private array $statuses = [
        '-1' => 'خطای سیستمی',
        '-9' => 'ارسال پارامتر نامعتبر',
        '-10' => 'کد مرچنت اجباری است',
        '-11' => 'کد مرچنت نامعتبر است',
        '-12' => 'مبلغ اجباری است',
        '-13' => 'حداقل مبلغ : 10000 ریال',
        '-14' => 'حداکثر مبلغ : 2000000000 ریال',
        '-15' => 'لینک بازگشت اجباری است',
        '-16' => 'لینک بازگشت نامعتبر است',
        '-17' => 'شماره موبایل نامعتبر است',
        '-18' => 'ایمیل نامعتبر است',
        '-19' => 'پارامتر ورودی برای کد ملی نامعتبر است',
        '-20' => 'پارامتر ورودی برای شماره کارت بانکی نامعتبر است',
        '-21' => 'حداکثر طول مجاز توضیحات 300 کاراکتر است',
        '-22' => 'فرمت پارامتر دیتا اختیاری باید Json باشد',
        '-23' => 'حداکثر طول مجاز برای دیتا اختیاری 500 کاراکتر است',
        '-24' => 'پارامتر ورودی ارسال پیامک نامعتبر است',
        '-25' => 'نام نامعتبر است',
        '-26' => 'در صورتی که ارسال پیامک فعال باشد، پارامتر شماره موبایل نمی تواند خالی باشد',
        '-27' => 'دامنه فعلی با دامنه تایید شده تطابق ندارد',
        '-28' => 'پذیرنده درگاه غیر فعال شده است',
        '-29' => 'درگاه پرداخت غیر فعال شده است',
        '-30' => 'درگاه پرداخت پیدا نشد',
        '-31' => 'تراکنش نامعتبر',
        '-102' => 'تراکنش منقضی شده است',
        '-101' => 'تراکنش ناموفق',
        '-100' => 'شماره کارت پرداختی با شماره کارت درخواستی تطابق ندارد',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'TabaPay';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.taba_pay.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $allowedCards = (array) $this->cfg('allowed_cards', []);
        $params = [
            'amount' => $amount,
            'callbackURL' => $this->callback,
            'mobile' => $this->cfg('mobile', ''),
            'cardNumber' => ! empty($allowedCards) ? current($allowedCards) : null,
            'nationalCode' => $this->cfg('national_id', ''),
            'description' => $trackingCode,
        ];
        $result = $this->request('post', $this->url . 'create', $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['token'];
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
        return 'https://tabapay.ir/pay/' . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'token' => $paymentToken,
            'amount' => $amount,
        ];
        $result = $this->request('post', $this->url . 'verify', $data);
        if (($result['status'] ?? null) != 'success') {
            throw new GatewayException($this->translateStatus($result['responseCode'] ?? null));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $result['cardNumber'] ?? $cardNumber;
        $paymentResponse->trackingCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->bankTrackingCode = isset($result['trackingCode']) ? (string) $result['trackingCode'] : null;
        $paymentResponse->referenceCode = isset($result['shaparakRefNumber']) ? (string) $result['shaparakRefNumber'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
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

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }

    private function request(string $method, string $url, array $data = [], array $headers = []): array
    {
        $headers['Authorization'] = 'Bearer ' . $this->cfg('token');

        return Http::timeout(10)
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw()
            ->json();
    }
}
