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
 * Bitpay (bitpay.ir) — ported from farayaz/larapay. Form-encoded REST, GET redirect.
 */
class Bitpay extends Base implements IPaymentChannel
{
    private string $url = 'http://bitpay.ir/payment/';

    private array $statuses = [
        'trans-id-gt-0' => 'آیدی تراکنش صحیح نمی باشد',
        '1' => 'پرداخت موفق',
        '-1' => 'دسترسی غیرمجاز',
        '-2' => 'آیدی تراکنش معتبر نمی باشد',
        '-3' => 'توکن ارسالی معتبر نمی باشد',
        '-4' => 'تراکنش پیدا نشد یا موفقیت آمیز نبوده است',
        '11' => 'تراکنش از قبل تایید شده است',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Bitpay';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.bitpay.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'api' => $this->cfg('api'),
            'amount' => $amount,
            'redirect' => $this->callback,
            'name' => 'موبایل : ' . $this->cfg('mobile', ''),
            'email' => '',
            'description' => $this->cfg('national_id', ''),
            'factorId' => $trackingCode,
        ];
        $result = $this->_request('post', 'gateway-send', $params);

        if ($result < 0) {
            throw new GatewayException($this->translateStatus($result));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->payUrl($paymentToken));
    }

    public function payUrl(string|int $paymentToken): string
    {
        return $this->_url('gateway-' . $paymentToken . '-get');
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'api' => $this->cfg('api'),
            'trans_id' => $trackingCode,
            'id_get' => $paymentToken,
            'json' => 1,
        ];

        $result = $this->_request('post', 'gateway-result-second', $data);

        if ($result['status'] != 1) {
            throw new GatewayException($this->translateStatus($result['status']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber ?? ($result['cardNum'] ?? null);
        $paymentResponse->trackingCode = isset($result['factorId']) ? (string) $result['factorId'] : null;
        $paymentResponse->referenceCode = (string) $paymentToken;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $default = [
            'trans_id' => null,
            'id_get' => null,
        ];
        $params = array_merge($default, $params);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['id_get'] ?? null;
        $paymentResponse->referenceCode = $params['id_get'] ?? null;
        $paymentResponse->trackingCode = $params['trans_id'] ?? null;
        $paymentResponse->paymentStatus = ($params['trans_id'] !== null && $params['trans_id'] >= 0 && $params['id_get'] !== null)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function _request(string $method, string $path, array $data = [], array $headers = [], $timeout = 10)
    {
        $url = $this->_url($path);

        if ($this->cfg('sandbox')) {
            $data['api'] = 'adxcv-zzadq-polkjsad-opp13opoz-1sdf455aadzmck1244567';
        }

        $response = Http::timeout($timeout)
            ->asForm()
            ->withHeaders($headers)
            ->{$method}($url, $data)
            ->throw();

        $decoded = $response->json();

        return $decoded !== null ? $decoded : $response->body();
    }

    private function _url($path)
    {
        $url = 'http://bitpay.ir/payment';
        if ($this->cfg('sandbox')) {
            $url .= '-test';
        }

        return $url . '/' . $path;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
