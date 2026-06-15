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
 * IranDargah (https://dargaah.com/) — ported from farayaz/larapay.
 */
class IranDargah extends Base implements IPaymentChannel
{
    private array $statuses = [
        '100' => 'تراکنش با موفقیت انجام ‌شده‌ است',
        '101' => 'تراکنش قبلا وریفای شده است',
        '200' => 'اتصال به درگاه بانک با موفقیت انجام ‌شده است',
        '201' => '‌در حال پرداخت در درگاه بانک',
        '403' => 'کد مرچنت صحیح نمی‌باشد',
        '404' => 'تراکنش یافت نشد',
        '-1' => 'کاربر از انجام تراکنش منصرف‌ شده است',
        '-2' => 'اطلاعات ارسالی صحیح نمی‌باشد',
        '-3' => 'URL همخوانی ندارد',
        '-4' => 'آدرس هدایت وجود ندارد',
        '-5' => 'آدرس هدایت معتبر نیست',
        '-6' => 'تراکنش وجود ندارد',
        '-7' => 'آدرس هدایت با آدرس سایت ثبت شده یکسان نیست',
        '-10' => 'مبلغ تراکنش کمتر از 50،000 ریال است',
        '-11' => 'مبلغ تراکنش با مبلغ پرداخت، یکسان نیست. مبلغ برگشت خورد',
        '-12' => 'شماره کارتی که با آن، تراکنش انجام ‌شده است با شماره کارت ارسالی، مغایرت دارد. مبلغ برگشت خورد',
        '-13' => 'تراکنش تکراری است',
        '-20' => 'شناسه تراکنش یافت‌ نشد',
        '-21' => 'مدت زمان مجاز، جهت ارسال به بانک گذشته‌است',
        '-22' => 'تراکنش برای بانک ارسال شده است',
        '-23' => 'خطا در اتصال به درگاه بانک',
        '-30' => 'اشکالی در فرایند پرداخت ایجاد ‌شده است. مبلغ برگشت خورد',
        '-31' => 'خطای ناشناخته',
        'failed' => 'ناموفق',
        'id-mismatch' => 'عدم تطبیق شناسه بازگشتی',
        'amount-mismatch' => 'عدم تطبیق مبلغ بازگشتی',
        'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
        'connection-exception' => 'خطا ارتباطی با سرویس دهنده',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'IranDargah';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.iran_dargah.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'amount' => $amount,
            'callbackURL' => $this->callback,
            'orderId' => $trackingCode,
            'cardNumber' => '',
            'mobile' => $this->cfg('mobile', ''),
            'description' => $trackingCode,
        ];
        $result = $this->request('payment', $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['authority'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $this->payUrl($result['authority']);
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
        return $this->url('ird/startpay/' . $paymentToken);
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'authority' => $paymentToken,
            'amount' => $amount,
            'orderId' => $trackingCode,
        ];
        $result = $this->request('verification', $data);
        if ($result['status'] != 100) {
            throw new GatewayException($this->translateStatus($result['status']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber ?? ($result['cardNumber'] ?? null);
        $paymentResponse->trackingCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->referenceCode = (string) $result['refId'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['authority'] ?? null;
        $paymentResponse->referenceCode = $params['orderId'] ?? null;
        $paymentResponse->cardNumber = $params['pan'] ?? null;
        $paymentResponse->paymentStatus = (($params['code'] ?? null) == 100)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function request(string $path, array $data = [], array $headers = []): array
    {
        $data['merchantID'] = $this->cfg('sandbox') ? 'TEST' : $this->cfg('merchant_id');

        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders($headers)
            ->post($this->url($path), $data)
            ->throw()
            ->json();
    }

    private function url(string $path): string
    {
        $url = 'https://dargaah.com/';
        if ($this->cfg('sandbox')) {
            $url .= 'sandbox/';
        }

        return $url . $path;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? '<null>'));
    }
}
