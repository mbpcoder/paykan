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
 * NextPay (https://nextpay.org/) — ported from farayaz/larapay.
 */
class NextPay extends Base implements IPaymentChannel
{
    private string $url = 'https://nextpay.org/nx/gateway/';

    private array $statuses = [
        '0' => 'پرداخت تکمیل و با موفقیت انجام شده است',
        '-1' => 'منتظر ارسال تراکنش و ادامه پرداخت',
        '-2' => 'پرداخت رد شده توسط کاربر یا بانک',
        '-3' => 'پرداخت در حال انتظار جواب بانک',
        '-4' => 'پرداخت لغو شده است',
        '-20' => 'کد api_key ارسال نشده است',
        '-21' => 'کد trans_id ارسال نشده است',
        '-22' => 'مبلغ ارسال نشده',
        '-23' => 'لینک ارسال نشده',
        '-24' => 'مبلغ صحیح نیست',
        '-25' => 'تراکنش قبلا انجام و قابل ارسال نیست',
        '-26' => 'مقدار توکن ارسال نشده است',
        '-27' => 'شماره سفارش صحیح نیست',
        '-28' => 'مقدار فیلد سفارشی [custom_json_fields] از نوع json نیست',
        '-29' => 'کد بازگشت مبلغ صحیح نیست',
        '-30' => 'مبلغ کمتر از حداقل پرداختی است',
        '-31' => 'صندوق کاربری موجود نیست',
        '-32' => 'مسیر بازگشت صحیح نیست',
        '-33' => 'کلید مجوز دهی صحیح نیست',
        '-34' => 'کد تراکنش صحیح نیست',
        '-35' => 'ساختار کلید مجوز دهی صحیح نیست',
        '-36' => 'شماره سفارش ارسال نشد است',
        '-37' => 'شماره تراکنش یافت نشد',
        '-38' => 'توکن ارسالی موجود نیست',
        '-39' => 'کلید مجوز دهی موجود نیست',
        '-40' => 'کلید مجوزدهی مسدود شده است',
        '-41' => 'خطا در دریافت پارامتر، شماره شناسایی صحت اعتبار که از بانک ارسال شده موجود نیست',
        '-42' => 'سیستم پرداخت دچار مشکل شده است',
        '-43' => 'درگاه پرداختی برای انجام درخواست یافت نشد',
        '-44' => 'پاسخ دریاف شده از بانک نامعتبر است',
        '-45' => 'سیستم پرداخت غیر فعال است',
        '-46' => 'درخواست نامعتبر',
        '-47' => 'کلید مجوز دهی یافت نشد [حذف شده]',
        '-48' => 'نرخ کمیسیون تعیین نشده است',
        '-49' => 'تراکنش مورد نظر تکراریست',
        '-50' => 'حساب کاربری برای صندوق مالی یافت نشد',
        '-51' => 'شناسه کاربری یافت نشد',
        '-52' => 'حساب کاربری تایید نشده است',
        '-60' => 'ایمیل صحیح نیست',
        '-61' => 'کد ملی صحیح نیست',
        '-62' => 'کد پستی صحیح نیست',
        '-63' => 'آدرس پستی صحیح نیست و یا بیش از ۱۵۰ کارکتر است',
        '-64' => 'توضیحات صحیح نیست و یا بیش از ۱۵۰ کارکتر است',
        '-65' => 'نام و نام خانوادگی صحیح نیست و یا بیش از ۳۵ کاکتر است',
        '-66' => 'تلفن صحیح نیست',
        '-67' => 'نام کاربری صحیح نیست یا بیش از ۳۰ کارکتر است',
        '-68' => 'نام محصول صحیح نیست و یا بیش از ۳۰ کارکتر است',
        '-69' => 'آدرس ارسالی برای بازگشت موفق صحیح نیست و یا بیش از ۱۰۰ کارکتر است',
        '-70' => 'آدرس ارسالی برای بازگشت ناموفق صحیح نیست و یا بیش از ۱۰۰ کارکتر است',
        '-71' => 'موبایل صحیح نیست',
        '-72' => 'بانک پاسخگو نبوده است لطفا با نکست پی تماس بگیرید',
        '-73' => 'مسیر بازگشت دارای خطا میباشد یا بسیار طولانیست',
        '-90' => 'بازگشت مبلغ بدرستی انجام شد',
        '-91' => 'عملیات ناموفق در بازگشت مبلغ',
        '-92' => 'در عملیات بازگشت مبلغ خطا رخ داده است',
        '-93' => 'موجودی صندوق کاربری برای بازگشت مبلغ کافی نیست',
        '-94' => 'کلید بازگشت مبلغ یافت نشد',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'NextPay';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.next_pay.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'api_key' => $this->cfg('api_key'),
            'order_id' => $trackingCode,
            'amount' => $amount,
            'callback_uri' => $this->callback,
            'currency' => 'IRR',
            'customer_phone' => $this->cfg('mobile', ''),
            'payer_desc' => $trackingCode,
            'auto_verify' => false,
        ];
        $result = $this->request('post', 'token', $params);
        if ($result['code'] !== -1) {
            throw new GatewayException($this->translateStatus($result['code']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['trans_id'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $this->payUrl($result['trans_id']);
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
            'api_key' => $this->cfg('api_key'),
            'trans_id' => $paymentToken,
            'amount' => $amount,
            'currency' => 'IRR',
        ];
        $result = $this->request('post', 'verify', $data);
        if ($result['code'] != 0) {
            throw new GatewayException($this->translateStatus($result['code']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber ?? ($result['card_holder'] ?? null);
        $paymentResponse->trackingCode = isset($result['Shaparak_Ref_Id']) ? (string) $result['Shaparak_Ref_Id'] : ($trackingCode !== null ? (string) $trackingCode : null);
        $paymentResponse->referenceCode = isset($result['Shaparak_Ref_Id']) ? (string) $result['Shaparak_Ref_Id'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['trans_id'] ?? null;
        $paymentResponse->trackingCode = isset($params['order_id']) ? (string) $params['order_id'] : null;
        $paymentResponse->paymentStatus = (($params['trans_id'] ?? null) !== null)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders($headers)
            ->{$method}($this->url . $path, $data)
            ->throw()
            ->json();
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
