<?php

namespace App\Channels\PaymentChannels\Exceptions;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Throwable;

class InvalidApiToken extends \Exception
{
    public function __construct($message = "Invalid api token", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}