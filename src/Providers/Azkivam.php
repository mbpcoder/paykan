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
 * Azkivam (ازکی‌وام) — ported from farayaz/larapay. REST/JSON, GET redirect.
 */
class Azkivam extends Base implements IPaymentChannel
{
    private string $url = 'https://api.azkivam.com';

    private array $statuses = [
        '0' => 'ازکی‌وام: Request finished successfully',
        '1' => 'ازکی‌وام: خطای داخلی اتفاق افتاده است.',
        '12' => 'ازکی‌وام: فروشگاه فعال نیست.',
        '13' => 'ازکی‌وام: شماره موبایل معتبر نیست.',
        '15' => 'ازکی‌وام: Access Denied',
        '16' => 'ازکی‌وام: Transaction already reversed',
        '17' => 'ازکی‌وام: Ticket Expired',
        '18' => 'ازکی‌وام: Signature Invalid',
        '19' => 'ازکی‌وام: Ticket unpayable',
        '2' => 'ازکی‌وام: Resource Not Found',
        '20' => 'ازکی‌وام: شماره موبایل مشتری با ثبت نام شده در درگاه ازکی‌وام یکسان نیست.',
        '21' => 'ازکی‌وام: اعتبار کافی نیست.',
        '28' => 'ازکی‌وام: تراکنش قابل تأیید نیست.',
        '32' => 'ازکی‌وام: Invalid Invoice Data',
        '33' => 'ازکی‌وام: Contract is not started',
        '34' => 'ازکی‌وام: Contract is expired',
        '4' => 'ازکی‌وام: Malformed Data',
        '44' => 'ازکی‌وام: Validation exception',
        '5' => 'ازکی‌وام: Data Not Found',
        '51' => 'ازکی‌وام: Request data is not valid',
        '59' => 'ازکی‌وام: Transaction not reversible',
        '60' => 'ازکی‌وام: Transaction must be in verified state',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Azkivam';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.azkivam.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'amount' => $amount,
            'redirect_uri' => $this->callback,
            'fallback_uri' => $this->callback,
            'provider_id' => $trackingCode,
            'mobile_number' => $this->cfg('mobile', ''),
            'merchant_id' => $this->cfg('merchant_id'),
            'items' => [],
        ];
        $result = $this->_request('/payment/purchase', $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['result']['ticket_id'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $this->payUrl($result['result']['ticket_id']);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->payUrl($paymentToken));
    }

    public function payUrl(string|int $paymentToken): string
    {
        return 'https://panel.azkivam.com/payment/?ticketId=' . $paymentToken;
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $params = [
            'ticket_id' => $paymentToken,
        ];
        $result = $this->_request('/payment/verify', $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = (string) $paymentToken;
        $paymentResponse->referenceCode = null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $default = [
            'status' => null,
        ];
        $params = array_merge($default, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentStatus = ($params['status'] == 'Done')
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function _request(string $url, array $data)
    {
        $plain = $url . '#' . time() . '#POST#' . $this->cfg('api_key');
        $signature = bin2hex(@openssl_encrypt($plain, 'AES-256-CBC', hex2bin($this->cfg('api_key')), OPENSSL_RAW_DATA));

        $result = Http::timeout(10)
            ->withHeaders([
                'Signature' => $signature,
                'MerchantId' => $this->cfg('merchant_id'),
            ])
            ->post($this->url . $url, $data)
            ->throw()
            ->json();

        if ($result['rsCode'] != '0') {
            throw new GatewayException($this->translateStatus($result['rsCode']));
        }

        return $result;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
