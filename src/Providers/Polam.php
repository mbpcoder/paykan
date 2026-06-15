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
 * Polam (https://polam.io/) — ported from farayaz/larapay.
 */
class Polam extends Base implements IPaymentChannel
{
    private string $url = 'https://polam.io/invoice/';

    private array $statuses = [
        '100' => 'نوع درخواست باید POST باشد',
        '101' => 'api_key ارسال نشده است یا صحیح نیست',
        '102' => 'مبلغ ارسال نشده است یا کمتر از 1000 ریال است',
        '103' => 'آدرس بازگشت ارسال نشده است',
        '301' => 'خطایی در برقراری با سرور بانک رخ داده است',
        '302' => 'ترمینال غیرفعال است.',
        '200' => 'شناسه پرداخت صحیح نیست',
        '201' => 'پرداخت انجام نشده است',
        '202' => 'پرداخت کنسل شده است یا خطایی در مراحل پرداخت رخ داده است.',
        '203' => 'آدرس بازگشت و یا آدرس درخواست کننده با دامنه ثبت شده یکی نیست.',
        '204' => 'آدرس آی پی هاست دامنه نامعتبر است.',

        'token-mismatch' => 'عدم تطبیق توکن',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Polam';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.polam.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'api_key' => $this->cfg('api_key'),
            'amount' => $amount,
            'return_url' => $this->callback,
        ];
        $result = $this->request('post', $this->url . 'request', $params);
        if ($result['status'] != 1) {
            throw new GatewayException($this->translateStatus($result['errorCode']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['invoice_key'];
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentUrl = $this->payUrl($result['invoice_key']);
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
        return $this->url . 'pay/' . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $url = $this->url . 'check/' . $paymentToken;
        $data = [
            'api_key' => $this->cfg('api_key'),
        ];
        $result = $this->request('post', $url, $data);
        if ($result['status'] != 1) {
            throw new GatewayException($this->translateStatus($result['errorCode']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = isset($result['bank_code']) ? (string) $result['bank_code'] : null;
        $paymentResponse->referenceCode = isset($result['bank_code']) ? (string) $result['bank_code'] : null;
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['invoice_key'] ?? null;
        $paymentResponse->paymentStatus = isset($params['invoice_key'])
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    public function fee(int $amount): int
    {
        $fee = 5_000;
        if ($amount > 500_000) {
            $fee = min(50_000, round($amount * 0.01));
        }

        return $fee;
    }

    private function request(string $method, string $url, array $data = [], array $headers = []): array
    {
        return Http::timeout(10)
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw()
            ->json();
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
