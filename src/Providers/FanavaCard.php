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
 * FanavaCard (fcp.shaparak.ir) — ported from farayaz/larapay.
 *
 * In larapay FanavaCard extends Omidpay; the inherited request/verify/_request
 * logic is inlined here with FanavaCard's URLs. POST-form redirect.
 */
class FanavaCard extends Base implements IPaymentChannel
{
    private string $url = 'https://fcp.shaparak.ir/ref-payment/RestServices/mts/';

    private array $statuses = [
        'erAAS_InvalidUseridOrPass' => 'نام کاربری یا رمز عبور نامعتبر',
        'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'FanavaCard';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.fanava_card.' . $key, $default);
    }

    #[\Override]
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
        $result = $this->_request('post', $url, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['Token'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), [
            'token' => $paymentToken,
            'language' => 'fa',
        ]);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return 'https://fcp.shaparak.ir/_ipgw_/payment/';
    }

    #[\Override]
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
        $result1 = $this->_request('post', $url, $data);

        $url = $this->url . 'verifyMerchantTrans/';
        $data = [
            'WSContext' => [
                'UserId' => $this->cfg('user_id'),
                'Password' => $this->cfg('password'),
            ],
            'Token' => $paymentToken,
            'RefNum' => $result1['RefNum'] ?? null,
        ];
        $result2 = $this->_request('post', $url, $data);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result2;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber ?? (isset($result1['MaskPan']) ? str_replace('********', '******', $result1['MaskPan']) : null);
        $paymentResponse->trackingCode = isset($result1['Rrn']) ? (string) $result1['Rrn'] : ($trackingCode !== null ? (string) $trackingCode : null);
        $paymentResponse->referenceCode = isset($result2['RefNum']) ? (string) $result2['RefNum'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $default = [
            'State' => null,
            'token' => null,
            'RefNum' => null,
        ];
        $params = array_merge($default, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['token'] ?? null;
        $paymentResponse->referenceCode = $params['RefNum'] ?? null;
        $paymentResponse->paymentStatus = (($params['State'] ?? null) === 'OK')
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function _request($method, $url, array $data = [], array $headers = [], $timeout = 10)
    {
        $result = Http::timeout($timeout)
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
