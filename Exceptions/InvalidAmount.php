<?php

namespace App\Channels\PaymentChannels\Exceptions;

use Exception;
use Throwable;

class InvalidAmount extends Exception
{
    public function __construct($message = "Invalid amount", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}