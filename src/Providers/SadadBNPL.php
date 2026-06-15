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
 * Sadad BNPL (buy-now-pay-later) — ported from farayaz/larapay.
 *
 * Verify uses the callback "Token" (not the request BnplKey): pass the callback
 * Token as $paymentToken.
 */
class SadadBNPL extends Base implements IPaymentChannel
{
    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'SadadBNPL';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.sadad_bnpl.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $mobile = $this->cfg('mobile', '');
        $data = [
            'TerminalId' => $this->cfg('terminal_id'),
            'MerchantId' => $this->cfg('merchant_id'),
            'Amount' => $amount,
            'OrderId' => $trackingCode,
            'ReturnUrl' => $this->callback,
            'ApplicationName' => 'Bnpl',
            'UserId' => $mobile,
            'CardHolderIdentity' => $mobile,
            'PanAuthenticationType' => 2,
            'LocalDateTime' => date('m/d/Y g:i:s a'),
            'NationalCode' => $this->cfg('national_id', ''),
        ];
        $result = $this->request('https://bnpl.sadadpsp.ir/Bnpl/GenerateKey', 'post', $data);
        if (($result['ResponseCode'] ?? null) != 0) {
            throw new GatewayException((string) ($result['Message'] ?? 'failed'));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result['BnplKey'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = 'https://bnpl.sadadpsp.ir/Home?key=' . $result['BnplKey'];
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->payUrl($paymentToken));
    }

    public function payUrl(string|int $paymentToken): string
    {
        return 'https://bnpl.sadadpsp.ir/Home?key=' . $paymentToken;
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'Token' => $paymentToken,
            'SignData' => $this->encryptPkcs7((string) $paymentToken),
        ];
        $result = $this->request('https://sadad.shaparak.ir/api/v0/BnplAdvice/Verify', 'post', $data);
        if (($result['ResCode'] ?? null) != 0) {
            throw new GatewayException((string) ($result['ResCode'] ?? 'failed'));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->trackingCode = isset($result['SystemTraceNo']) ? (string) $result['SystemTraceNo'] : null;
        $paymentResponse->referenceCode = isset($result['RetrivalRefNo']) ? (string) $result['RetrivalRefNo'] : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['Token'] ?? null;
        $paymentResponse->trackingCode = isset($params['OrderId']) ? (string) $params['OrderId'] : null;
        $paymentResponse->providerCode = $params['ResCode'] ?? null;
        $paymentResponse->paymentStatus = (($params['ResCode'] ?? null) == 0)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function request(string $url, string $method, array $data = [], array $headers = []): array
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw()
            ->json();
    }

    private function encryptPkcs7(string $str): string
    {
        $key = base64_decode($this->cfg('key'));
        $ciphertext = openssl_encrypt($str, 'DES-EDE3', $key, OPENSSL_RAW_DATA);

        return base64_encode($ciphertext);
    }
}
