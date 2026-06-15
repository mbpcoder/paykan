<?php

namespace MbpCoder\IranPayment\Exceptions;

use Throwable;

class InvalidRequestParams extends \Exception
{
    public function __construct($message = "Invalid request params", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}