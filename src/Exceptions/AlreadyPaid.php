<?php

namespace MbpCoder\Payment\Exceptions;

use Throwable;

class AlreadyPaid extends \Exception
{
    public function __construct($message = "Already paid!", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
