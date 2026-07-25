<?php

namespace MbpCoder\Payment\Support\Soap;

use SoapClient;

/**
 * A SoapClient stand-in that never touches the network. It skips the real
 * constructor (which would fetch the WSDL) and serves queued responses from
 * SoapClientFactory for any method call.
 */
class FakeSoapClient extends SoapClient
{
    public function __construct()
    {
        // Intentionally does not call parent::__construct() to avoid
        // fetching a real WSDL or opening a connection.
    }

    #[\Override]
    public function __call(string $name, array $arguments): mixed
    {
        return SoapClientFactory::dequeue($name);
    }
}
