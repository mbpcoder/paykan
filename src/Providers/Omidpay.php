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
 * Omidpay (Sepehr / omidpayment.ir) — ported from farayaz/larapay.
 */
class Omidpay extends Base implements IPaymentChannel
{
    private string $url = 'https://ref.omidpayment.ir/ref-payment/RestServices/mts/';

    private array $statuses = [
        'erAAS_InvalidUseridOrPass' => 'نام کاربری یا رمز عبور نامعتبر',
        'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Omidpay';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.omidpay.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $url = $this->url . 'generateTokenWithNoSign/';
        $params = [
            'WSContext' => [
                'UserId' => $this->cfg('user_id'),
                'Password' => $this->cfg('password'),
            ],
            'TransType' => 'EN_GOODS',
            'ReserveNum' => $trackingCode,
            'MerchantId' => $this->cfg('user_id'),
            'Amount' => $amount . '',
            'RedirectUrl' => $this->callback,
        ];
        $result = $this->request('post', $url, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['Token'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), [
            'token' => $paymentToken,
            'language' => 'fa',
        ]);
    }

    public function payUrl(string|int $paymentToken): string
    {
        return 'https://omid.shaparak.ir/_ipgw_/MainTemplate/payment/';
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $url = $this->url . 'inquiryMerchantToken/';
        $data = [
            'WSContext' => [
                'UserId' => $this->cfg('user_id'),
                'Password' => $this->cfg('password'),
            ],
            'Token' => $paymentToken,
        ];
        $result1 = $this->request('post', $url, $data);

        $url = $this->url . 'verifyMerchantTrans/';
        $data = [
            'WSContext' => [
                'UserId' => $this->cfg('user_id'),
                'Password' => $this->cfg('password'),
            ],
            'Token' => $paymentToken,
            'RefNum' => $cardNumber,
        ];
        $result2 = $this->request('post', $url, $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result2;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = str_replace('********', '******', $result1['MaskPan'] ?? '');
        $paymentResponse->trackingCode = isset($result1['Rrn']) ? (string) $result1['Rrn'] : ($trackingCode !== null ? (string) $trackingCode : null);
        $paymentResponse->referenceCode = isset($result2['RefNum']) ? (string) $result2['RefNum'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['token'] ?? null;
        $paymentResponse->referenceCode = $params['RefNum'] ?? null;
        $paymentResponse->paymentStatus = (($params['State'] ?? null) === 'OK')
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
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw()
            ->json();

        if (($result['Result'] ?? null) != 'erSucceed') {
            throw new GatewayException($this->translateStatus($result['Result'] ?? 'failed'));
        }

        return $result;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
