<?php

namespace MbpCoder\IranPayment\Exceptions;

use Throwable;

class InvalidApiToken extends \Exception
{
    public function __construct($message = "Invalid api token", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}