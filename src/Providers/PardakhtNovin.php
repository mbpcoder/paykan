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
 * Pardakht Novin Arian (PNA — pna.shaparak.ir) — ported from farayaz/larapay.
 *
 * Verify needs both the Token and the callback RefNum: pass the Token as
 * $paymentToken and the callback RefNum as $trackingCode.
 */
class PardakhtNovin extends Base implements IPaymentChannel
{
    private string $url = 'https://pna.shaparak.ir/';

    private array $statuses = [
        'Canceled By User' => 'لغو شده توسط مشتری',
        'erAAS_InvalidUseridOrPass' => 'کد کاربری یا رمز عبور صحیح نیست',
        'erMts_InvalidUseridOrPass' => 'رمز یا کد کاربری معتبر نمی‌باشد',
        'token-mismatch' => 'مغایرت توکن بازگشتی',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'PardakhtNovin';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.pardakht_novin.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'WSContext' => [
                'UserId' => $this->cfg('userId'),
                'Password' => $this->cfg('password'),
            ],
            'TransType' => 'EN_GOODS',
            'ReserveNum' => $trackingCode,
            'Amount' => $amount,
            'TerminalId' => $this->cfg('terminalId'),
            'RedirectUrl' => $this->callback,
        ];
        $result = $this->request('post', $this->url . 'ref-payment2/RestServices/mts/generateTokenWithNoSign/', $params);

        if (($result['Result'] ?? null) != 'erSucceed') {
            throw new GatewayException($this->translateStatus($result['Result'] ?? null));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['Token'];
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), ['token' => $paymentToken]);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $this->url . '_ipgw_/payment/';
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'WSContext' => [
                'UserId' => $this->cfg('userId'),
                'Password' => $this->cfg('password'),
            ],
            'Token' => $paymentToken,
            'RefNum' => $trackingCode,
        ];
        $result = $this->request('post', $this->url . 'ref-payment2/RestServices/mts/verifyMerchantTrans/', $data);

        if (($result['Result'] ?? null) != 'erSucceed') {
            throw new GatewayException($this->translateStatus($result['Result'] ?? null));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->referenceCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->wage = $this->fee((int) $amount);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['token'] ?? null;
        $paymentResponse->cardNumber = $params['CardMaskPan'] ?? null;
        $paymentResponse->trackingCode = $params['CustomerRefNum'] ?? null;
        $paymentResponse->referenceCode = $params['RefNum'] ?? ($params['TraceNo'] ?? null);
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

    private function request(string $method, string $url, array $data = [], array $headers = [], int $timeout = 10): array
    {
        return Http::timeout($timeout)
            ->acceptJson()
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
