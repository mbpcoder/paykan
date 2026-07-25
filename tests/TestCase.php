<?php

namespace MbpCoder\Payment\Tests;

use GuzzleHttp\Psr7\Response;
use MbpCoder\Payment\Config\ArrayConfigRepository;
use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Support\Http\ClientFactory;
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

    protected function jsonResponse(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }
}
