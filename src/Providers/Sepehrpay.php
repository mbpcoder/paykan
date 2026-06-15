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
 * Sepehrpay (Saderat/Sepehr) — ported from farayaz/larapay.
 * Uses a POST-form (View::make -> FormRedirect) redirect.
 *
 * Verify flow adaptation: the callback fields (digitalreceipt, cardnumber,
 * rrn, tracenumber, ...) are passed via processCallback; verify() takes the
 * digitalreceipt as $paymentToken to call the Advice endpoint.
 */
class Sepehrpay extends Base implements IPaymentChannel
{
    private string $url = 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/';

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

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $url = $this->url . 'GetToken';
        $params = [
            'Amount' => $amount,
            'callbackURL' => $this->callback,
            'invoiceID' => $trackingCode,
            'terminalID' => $this->cfg('terminalId'),
        ];

        $result = $this->request($url, $params);
        if ($result['Status'] != 0) {
            throw new GatewayException($this->translateStatus($result['Status']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['Accesstoken'];
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), [
            'token' => $paymentToken,
            'terminalID' => $this->cfg('terminalId'),
        ]);
    }

    public function payUrl(string|int $paymentToken): string
    {
        return 'https://sepehr.shaparak.ir:8080';
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $url = $this->url . 'Advice';
        $data = [
            'Tid' => $this->cfg('terminalId'),
            'digitalreceipt' => $paymentToken,
        ];
        $result = $this->request($url, $data);

        if (! ($result['Status'] == 'Ok' && $result['ReturnId'] == $amount)) {
            throw new GatewayException($this->translateStatus($result['Status']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->referenceCode = isset($result['ReturnId']) ? (string) $result['ReturnId'] : null;
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['digitalreceipt'] ?? ($params['trackId'] ?? null);
        $paymentResponse->cardNumber = $params['cardnumber'] ?? null;
        $paymentResponse->referenceCode = $params['rrn'] ?? null;
        $paymentResponse->trackingCode = $params['tracenumber'] ?? null;
        $paymentResponse->paymentStatus = (($params['respcode'] ?? null) == 0)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

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
