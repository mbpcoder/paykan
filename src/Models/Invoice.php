<?php

namespace MbpCoder\Payment\Models;

class Invoice
{
    public int $referenceId;

    public int $amount;

    public string|null $paymentToken = null;

    public string|null $ipgReferenceToken = null;

    public array $extraData = [];
}
