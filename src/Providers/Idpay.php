<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;

class Idpay extends Base implements IPaymentChannel
{
    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        return $paymentResponse;
    }

    #[\Override]
    public function pay($paymentToken)
    {
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $paymentToken;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        return $paymentResponse;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url . '?amount=' . ($amount * 10);
    }


}
