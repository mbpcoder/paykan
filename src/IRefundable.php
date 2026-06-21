<?php

namespace MbpCoder\Payment;

/**
 * Implemented by gateway providers that support reversing/refunding a
 * previously verified transaction.
 */
interface IRefundable
{
    public function refund(string|int $paymentToken, int $amount, string|int|null $trackingCode = null): bool;
}
