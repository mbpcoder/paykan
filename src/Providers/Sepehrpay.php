<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Exceptions\GatewayException;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\IRefundable;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\FormRedirect;
use MbpCoder\Payment\Support\Http\Http;

/**
 * Sepehrpay (Saderat/Sepehr) — ported from farayaz/larapay.
 * Uses a POST-form (View::make -> FormRedirect) redirect.
 *
 * Verify flow adaptation: the callback fields (digitalreceipt, cardnumber,
 * rrn, tracenumber, ...) are passed via processCallback; verify() takes the
 * digitalreceipt as $paymentToken to call the Advice endpoint.
 */
class Sepehrpay extends Base implements IPaymentChannel, IRefundable
{
    private string $url = 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/';

    private string $payBaseUrl = 'https://sepehr.shaparak.ir:8080';

    private array $statuses = [
        '-1' => 'تراکنش پیدا نشد.',
        '-2' => 'عدم تطابق ip / تراکنش قبلا Reserve شده است.',
        '-3' => 'Exception خطای - عمومی خطای Total Error',
        '-4' => 'امکان درخواست برای این تراکنش وجود ندارد.',
        '-5' => 'آدرس IP نامعتبر می‌باشد.',
        '-6' => 'عدم فعال بودن سرویس برگشت تراکنش برای پذیرنده',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Sepehrpay';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.sepehrpay.' . $key, $default);
    }

    private function baseUrl(): string
    {
        $base = $this->cfg('base_url');

        return $base !== null ? rtrim($base, '/') . '/' : $this->url;
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $url = $this->baseUrl() . 'GetToken';
        $params = [
            'Amount' => $amount,
            'callbackURL' => $this->callback,
            'invoiceID' => $trackingCode,
            'terminalID' => $this->cfg('terminalId'),
        ];

        $result = $this->request($url, $params);
        $success = ($result['Status'] ?? null) == 0;

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->providerCode = isset($result['Status']) ? (string) $result['Status'] : null;
        $paymentResponse->providerMessage = $success ? null : $this->translateStatus($result['Status'] ?? null);
        $paymentResponse->paymentToken = $success ? ($result['Accesstoken'] ?? null) : null;
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentStatus = $success ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), [
            'token' => $paymentToken,
            'terminalID' => $this->cfg('terminalId'),
        ]);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $this->cfg('pay_base_url') ?? $this->payBaseUrl;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $url = $this->baseUrl() . 'Advice';
        $data = [
            'Tid' => $this->cfg('terminalId'),
            'digitalreceipt' => $paymentToken,
        ];
        $result = $this->request($url, $data);
        $success = ($result['Status'] ?? null) == 'Ok' && ($result['ReturnId'] ?? null) == $amount;

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->referenceCode = isset($result['ReturnId']) ? (string) $result['ReturnId'] : null;
        $paymentResponse->providerCode = isset($result['Status']) ? (string) $result['Status'] : null;
        $paymentResponse->providerMessage = $success ? null : $this->translateStatus($result['Status'] ?? null);
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentStatus = $success ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function refund(string|int $paymentToken, int $amount, string|int|null $trackingCode = null): bool
    {
        $url = $this->baseUrl() . 'ReverseTransaction';
        $data = [
            'Tid' => $this->cfg('terminalId'),
            'digitalreceipt' => $paymentToken,
        ];
        $result = $this->request($url, $data);

        return ($result['Status'] ?? null) == 0;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->trackingCode = $params['payment_id'] ?? null;
        $paymentResponse->paymentToken = $params['digitalreceipt'] ?? null;
        $paymentResponse->cardNumber = $params['cardnumber'] ?? null;
        $paymentResponse->referenceCode = $params['digitalreceipt'] ?? null;
        $paymentResponse->traceNumber = $params['rrn'] ?? null;
        $paymentResponse->providerCode = $params['respcode'] ?? null;
        $paymentResponse->paymentStatus = (($params['respcode'] ?? null) == 0)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function fee(int $amount): int
    {
        $fee = 1_200;
        if ($amount >= 6_000_000) {
            $fee = (int) min(40_000, round($amount * 0.0002));
        }

        return $fee;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }

    private function request(string $url, array $data): array
    {
        return Http::timeout(10)
            ->post($url, $data)
            ->throw()
            ->json();
    }
}
