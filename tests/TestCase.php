<?php

namespace MbpCoder\Payment\Tests;

use GuzzleHttp\Psr7\Response;
use MbpCoder\Payment\Config\ArrayConfigRepository;
use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Support\Http\ClientFactory;
use MbpCoder\Payment\Support\Soap\SoapClientFactory;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::setRepository(new ArrayConfigRepository());
    }

    protected function tearDown(): void
    {
        ClientFactory::reset();
        SoapClientFactory::reset();
        parent::tearDown();
    }

    /**
     * Queue fake HTTP responses for every provider created for the rest of
     * this test, regardless of which provider or how it builds its request.
     */
    protected function fakeHttp(array $responses): void
    {
        ClientFactory::fake($responses);
    }

    /**
     * Queue fake SOAP responses (or exceptions) for every SOAP-based
     * provider (BehPardakht, PEC) for the rest of this test.
     */
    protected function fakeSoap(array $responses): void
    {
        SoapClientFactory::fake($responses);
    }

    protected function jsonResponse(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }
}
