<?php

namespace MbpCoder\Payment\Models;

class PaymentResponse
{
    // unique and one time usage provider payment token
    public string|null $paymentToken = null;

    // tracking code of application
    public string|null $trackingCode = null;

    // tracking code of bank
    public string|null $bankTrackingCode = null;

    // The unique identifier of the transaction
    public string|null $referenceCode = null;
    public string|null $cardNumber = null;
    public string|null $payerIp = null;
    public int|null $amount = null;
    public int|null $wage = null;
    public string|null $message = null;
    public string|null $providerMessage = null;
    public string|null $providerCode = null;
    public string|null $paymentUrl = null;
    public mixed $originalResponse = null;

    public PaymentStatus $paymentStatus;

    public function isSuccess(): bool
    {
        return ($this->paymentStatus === PaymentStatus::SUCCESS);
    }

    public function isFailed(): bool
    {
        return !$this->isSuccess();
    }
}
