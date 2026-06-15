<?php

namespace MbpCoder\IranPayment\Models;

enum PaymentStatus: string
{
    case SUCCESS = 'success';
    case PAID = 'paid';
    case FAILED = 'failed';
}
