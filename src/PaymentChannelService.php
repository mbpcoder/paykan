<?php

namespace MbpCoder\Payment;

use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Providers\Idpay;
use MbpCoder\Payment\Support\Str;

class PaymentChannelService implements IPaymentChannel
{
    private IPaymentChannel|null $gateway = null;

    /**
     * PaymentChannelService constructor.
     * @param null $name
     */
    public function __construct(string|null $name = null, string|null $token = null)
    {
        if ($name !== null && $this->isUrl($name)) {
            $this->gateway = ($name == null) ? $this->getDefaultChannel() : $this->getChannelByUrl($name, $token);
        } else {
            $this->gateway = ($name == null) ? $this->getDefaultChannel() : $this->getChannel($name, $token);
        }
    }

    private function isUrl(string $str): string
    {
        return Str::contains($str, ['//', 'http', 'https', 'www', '/']);
    }

    /**
     * @return IPaymentChannel|null
     */
    public function getDefaultChannel(): IPaymentChannel|null
    {
        return $this->getChannel(\MbpCoder\Payment\Config\Config::get('channels.ipg.default'));
    }

    /**
     * @param $name
     * @param $token
     * @return IPaymentChannel|null
     */
    private function getChannel($name, string|null $token = null): IPaymentChannel|null
    {
        if ($name == 'idpay') {
            return new Idpay();
        }
        $className = "\\MbpCoder\\Payment\\Providers\\" . ucfirst(Str::camel($name));
        if (class_exists($className)) {
            return new $className($token);
        }
        return null;
    }

    /**
     * @param string $url
     * @param string|null $token
     * @return IPaymentChannel|null
     */
    private function getChannelByUrl(string $url, string|null $token = null): IPaymentChannel|null
    {
        if (Str::contains($url, 'zarinp')) {
            $name = 'zarinpal';
        } else if (Str::contains($url, 'idpay')) {
            $name = 'idpay';
        } else if (Str::contains($url, 'pay.ir')) {
            $name = 'pay';
        } else {
            $str = str_replace(['https://', 'https://www.', 'http://', 'http://www.'], '', $url);
            $name = str_split($str, strpos($str, '.'))[0];
        }
        return $this->getChannel($name, $token);
    }

    /**
     * @param $callbackUrl
     */
    public function setCallbackUrl($callbackUrl): void
    {
        $this->gateway->setCallbackUrl($callbackUrl);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        return $this->gateway->initial($amount, $trackingCode, $description);
    }

    public function pay(string|int $paymentToken)
    {
        return $this->gateway->pay($paymentToken);
    }

    public function payUrl(int|string $paymentToken): string
    {
        return $this->gateway->payUrl($paymentToken);
    }

    public function processCallback(array $params): PaymentResponse
    {
        return $this->gateway->processCallback($params);
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        return $this->gateway->verify($paymentToken, $amount, $cardNumber, $trackingCode);
    }

    public function personalPaymentPage($url, $amount, $name = null, $phone = null, $description = null)
    {
        if (!is_null($this->gateway)) {
            return $this->gateway->personalPaymentPage($url, $amount, $name, $phone, $description);
        }
        return $url;
    }
}
