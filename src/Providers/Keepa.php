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
 * Keepa (https://api.kipaa.ir/) — ported from farayaz/larapay.
 * Uses a bearer token and a POST-form redirect.
 */
class Keepa extends Base implements IPaymentChannel
{
    private string $url = 'https://api.kipaa.ir/ipg/v1/supplier/';

    private array $statuses = [
        'amount-mismatch' => 'مغایرت مبلغ پرداختی',
        'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
        'verify-status-false' => 'تایید اولیه نا موفق',
        'confirm-status-false' => 'تایید ثانویه نا موفق',

        100 => 'تراکنش با موفقیت ایجاد شد.',
        101 => 'خطا در ثبت تراکنش',
        102 => 'انصراف کاربر در مراحل میانی پرداخت',
        103 => 'بازگشت به سایت پذیرنده',

        200 => 'عملیات با موفقیت انجام شد.',
        404 => 'آدرس URL درخواستی شما وجود ندارد.',
        405 => 'توکن نامعتبر است.',
        406 => 'مقادیر ورودی قابل پردازش نیست.',
        416 => 'مبلغ وارد شده نامعتبر است.',
        500 => 'خطایی در سرور رخ داده است. لطفا بعدا تلاش کنید.',
        503 => 'سرویس به صورت موقت در دسترس نمی‌باشد.',

        'failed' => 'ناموفق',
        'connection-exception' => 'خطا ارتباطی با سرویس دهنده',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Keepa';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.keepa.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $url = $this->url . 'request_payment_token';
        $params = [
            'Amount' => $amount,
            'CallBack_Url' => $this->callback,
            'mobile' => $this->cfg('mobile', ''),
            'Details' => $trackingCode,
        ];
        $result = $this->request('post', $url, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['Content']['payment_token'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), ['payment_token' => $paymentToken]);
    }

    public function payUrl(string|int $paymentToken): string
    {
        return 'https://ipg.kipaa.ir';
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'payment_token' => $paymentToken,
            'reciept_number' => $this->recieptNumber,
        ];

        $url = $this->url . 'verify_transaction';
        $result = $this->request('post', $url, $data);
        if ($result['Content']['Status'] != true) {
            throw new GatewayException($this->translateStatus('verify-status-false'));
        }
        if ($amount != $result['Content']['Amount']) {
            throw new GatewayException($this->translateStatus('amount-mismatch'));
        }

        $url = $this->url . 'confirm_transaction';
        $result = $this->request('post', $url, $data);
        if ($result['Content']['Status'] != true) {
            throw new GatewayException($this->translateStatus('confirm-status-false'));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = $this->recieptNumber !== null ? (string) $this->recieptNumber : null;
        $paymentResponse->referenceCode = (string) $result['Content']['ConfirmTransactionNumber'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    private string|int|null $recieptNumber = null;

    public function processCallback(array $params): PaymentResponse
    {
        $this->recieptNumber = $params['reciept_number'] ?? null;

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['payment_token'] ?? null;
        $paymentResponse->referenceCode = $params['reciept_number'] ?? null;
        $paymentResponse->paymentStatus = (($params['state'] ?? null) == 100)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function request(string $method, string $url, array $data = [], array $headers = []): array
    {
        $result = Http::timeout(10)
            ->withToken($this->cfg('token'))
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw()
            ->json();

        if (! ($result['Success'] ?? false)) {
            throw new GatewayException($this->translateStatus($result['Message'] ?? ($result['Status'] ?? 'failed')));
        }

        return $result;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? '<null>'));
    }
}
