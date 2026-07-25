<?php

namespace MbpCoder\Payment\Support\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

/**
 * Single point through which every provider obtains its Guzzle client.
 *
 * In normal operation this just returns `new Client($config)`. In tests,
 * `ClientFactory::fake()` installs a MockHandler so that every provider
 * (regardless of how it builds its request) receives canned responses
 * instead of hitting the real gateway.
 */
class ClientFactory
{
    private static ?MockHandler $mockHandler = null;

    public static function make(array $config = []): Client
    {
        if (self::$mockHandler !== null) {
            $config['handler'] = HandlerStack::create(self::$mockHandler);
        }

        return new Client($config);
    }

    /**
     * Install a queue of fake responses. Every client created via make()
     * while faking is active will be served from this queue, in order.
     *
     * @param array<\Psr\Http\Message\ResponseInterface|\GuzzleHttp\Exception\GuzzleException> $responses
     */
    public static function fake(array $responses): void
    {
        self::$mockHandler = new MockHandler($responses);
    }

    /**
     * Append more responses to the currently installed mock queue.
     */
    public static function append(mixed ...$responses): void
    {
        self::$mockHandler?->append(...$responses);
    }

    public static function isFaking(): bool
    {
        return self::$mockHandler !== null;
    }

    /**
     * Restore normal (real) client creation.
     */
    public static function reset(): void
    {
        self::$mockHandler = null;
    }
}
