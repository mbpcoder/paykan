<?php

namespace App\Channels\PaymentChannels\Models;

enum PaymentStatus: string
{
    case SUCCESS = 'success';
    case PAID = 'paid';
    case FAILED = 'failed';
}
