<?php

namespace MbpCoder\Payment\Exceptions;

use Throwable;

/**
 * Thrown when a payment gateway returns an error or cannot be reached.
 */
class GatewayException extends \Exception
{
    public function __construct(string $message = 'Gateway error', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
