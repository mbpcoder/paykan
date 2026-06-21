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
 * Sadad (Melli, https://sadad.shaparak.ir/) — ported from farayaz/larapay.
 *
 * Verify flow adaptation: $paymentToken carries the gateway Token returned on
 * the callback, $trackingCode carries the original order id, and $cardNumber
 * carries the PrimaryAccNo (masked PAN) from the callback.
 */
class Sadad extends Base implements IPaymentChannel
{
    private string $url = 'https://sadad.shaparak.ir/vpg/api/v0/';

    private string $payBaseUrl = 'https://sadad.shaparak.ir/VPG/Purchase';

    private array $statuses = [];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Sadad';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.sadad.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $data = [
            'TerminalId' => $this->cfg('terminal_id'),
            'MerchantId' => $this->cfg('merchant_id'),
            'Amount' => $amount,
            'SignData' => $this->encryptPkcs7($this->cfg('terminal_id') . ';' . $trackingCode . ';' . $amount),
            'ReturnUrl' => $this->callback,
            'LocalDateTime' => date('m/d/Y g:i:s a'),
            'OrderId' => $trackingCode,
        ];
        $result = $this->request('post', 'Request/PaymentRequest', $data);
        $success = ($result['ResCode'] ?? null) == 0;

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->providerCode = isset($result['ResCode']) ? (string) $result['ResCode'] : null;
        $paymentResponse->providerMessage = $success ? null : ($result['Description'] ?? null);
        $paymentResponse->paymentToken = $success ? ($result['Token'] ?? null) : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $success ? $this->payUrl($result['Token']) : null;
        $paymentResponse->paymentStatus = $success ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
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
        return ($this->cfg('pay_base_url') ?? $this->payBaseUrl) . '?Token=' . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'Token' => $paymentToken,
            'SignData' => $this->encryptPkcs7((string) $paymentToken),
        ];
        $result = $this->request('post', 'Advice/Verify', $data);
        $success = ($result['ResCode'] ?? null) == 0;

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = isset($result['SystemTraceNo']) ? (string) $result['SystemTraceNo'] : null;
        $paymentResponse->referenceCode = isset($result['RetrivalRefNo']) ? (string) $result['RetrivalRefNo'] : null;
        $paymentResponse->providerCode = isset($result['ResCode']) ? (string) $result['ResCode'] : null;
        $paymentResponse->providerMessage = $success ? null : $this->translateStatus($result['ResCode'] ?? null);
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = $success ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['token'] ?? null;
        $paymentResponse->traceNumber = $params['token'] ?? null;
        $paymentResponse->trackingCode = isset($params['OrderId']) ? (string) $params['OrderId'] : null;
        $paymentResponse->cardNumber = $params['PrimaryAccNo'] ?? null;
        $paymentResponse->providerCode = isset($params['ResCode']) ? (string) $params['ResCode'] : null;
        $paymentResponse->paymentStatus = (($params['ResCode'] ?? null) == 0 && isset($params['ResCode']))
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        return Http::timeout(10)
            ->withHeaders($headers)
            ->{$method}(rtrim($this->cfg('base_url') ?? $this->url, '/') . '/' . $path, $data)
            ->throw()
            ->json();
    }

    private function encryptPkcs7(string $str): string
    {
        $key = base64_decode($this->cfg('key'));
        $ciphertext = openssl_encrypt($str, 'DES-EDE3', $key, OPENSSL_RAW_DATA);

        return base64_encode($ciphertext);
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
