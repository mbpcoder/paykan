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
 * Zibal (https://gateway.zibal.ir/) — ported from farayaz/larapay.
 */
class Zibal extends Base implements IPaymentChannel
{
    private string $url = 'https://gateway.zibal.ir/';

    private array $statuses = [
        '-1' => 'در انتظار پردخت',
        '-2' => 'خطای داخلی',
        '1' => 'پرداخت شده - تاییدشده',
        '2' => 'پرداخت شده - تاییدنشده',
        '3' => 'لغوشده توسط کاربر',
        '4' => '‌شماره کارت نامعتبر می‌باشد.',
        '5' => '‌موجودی حساب کافی نمی‌باشد.',
        '6' => 'رمز واردشده اشتباه می‌باشد.',
        '7' => '‌تعداد درخواست‌ها بیش از حد مجاز می‌باشد.',
        '8' => '‌تعداد پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد.',
        '9' => 'مبلغ پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد.',
        '10' => '‌صادرکننده‌ی کارت نامعتبر می‌باشد.',
        '11' => '‌خطای سوییچ',
        '12' => 'کارت قابل دسترسی نمی‌باشد.',
        '100' => 'با موفقیت تایید شد.',
        '102' => 'merchant یافت نشد.',
        '103' => 'merchant غیرفعال',
        '104' => 'merchant نامعتبر',
        '105' => 'amount بایستی بزرگتر از 1,000 ریال باشد.',
        '106' => 'callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)',
        '113' => 'amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.',
        '201' => 'قبلا تایید شده',
        '202' => 'سفارش پرداخت نشده یا ناموفق بوده است.',
        '203' => 'trackId نامعتبر می‌باشد.',
        'token-mismatch' => 'عدم تطبیق توکن',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Zibal';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.zibal.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'merchant' => $this->cfg('merchant'),
            'amount' => $amount,
            'callbackUrl' => $this->callback,
            'description' => $description,
            'orderId' => $trackingCode,
            'mobile' => null,
            'allowedCards' => null,
            'ledgerId' => null,
            'linkToPay' => null,
            'sms' => null,
        ];
        $result = $this->request('post', $this->url . 'v1/request', $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['trackId'];
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentUrl = $this->url . 'start/' . $result['trackId'];
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
        return $this->url . 'start/' . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'merchant' => $this->cfg('merchant'),
            'trackId' => $paymentToken,
        ];
        $result = $this->request('post', $this->url . 'v1/verify', $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->providerMessage = $this->translateStatus($result['status'] ?? null);
        $paymentResponse->cardNumber = $result['cardNumber'] ?? null;
        $paymentResponse->trackingCode = isset($result['refNumber']) ? (string) $result['refNumber'] : null;
        $paymentResponse->referenceCode = isset($result['refNumber']) ? (string) $result['refNumber'] : null;
        $paymentResponse->wage = $this->fee((int) $amount);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['trackId'] ?? null;
        $paymentResponse->providerCode = $params['status'] ?? null;
        $paymentResponse->providerMessage = $this->translateStatus($params['status'] ?? null);
        $paymentResponse->paymentStatus = (($params['success'] ?? null) == 1)
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

        if (($result['result'] ?? null) != 100) {
            throw new GatewayException($this->translateStatus($result['result'] ?? null));
        }

        return $result;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
