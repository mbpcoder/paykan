<?php

namespace MbpCoder\IranPayment;


use MbpCoder\IranPayment\Models\PaymentResponse;

interface IPaymentChannel
{
    /**
     * @param string $callbackUrl
     * @return void
     */
    public function setCallbackUrl(string $callbackUrl): void;

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse;

    public function pay(string|int $paymentToken);

    public function payUrl(string|int $paymentToken): string;

    public function verify(string|int $paymentToken, int $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse;

    public function processCallback(array $params): PaymentResponse;

    public function personalPaymentPage($url, $amount, $name, $phone, $description);
}
