<?php

namespace App\Channels\PaymentChannels\Exceptions;

use Exception;
use Throwable;

class LessThanLimitAmount extends Exception
{
    public function __construct($message = "less than limit amount", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}