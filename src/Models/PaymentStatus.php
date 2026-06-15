<?php

namespace MbpCoder\Payment\Models;

enum PaymentStatus: string
{
    case SUCCESS = 'success';
    case PAID = 'paid';
    case FAILED = 'failed';
}
