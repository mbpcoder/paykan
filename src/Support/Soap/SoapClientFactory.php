<?php

namespace MbpCoder\Payment\Support\Soap;

use SoapClient;

/**
 * Single point through which every SOAP-based provider (BehPardakht, PEC)
 * obtains its SoapClient.
 *
 * In normal operation this just returns `new SoapClient($wsdl, $options)`.
 * In tests, `SoapClientFactory::fake()` installs a queue of canned
 * responses (or exceptions) so providers can be exercised without a real
 * WSDL fetch or network call.
 */
class SoapClientFactory
{
    /** @var array<int, mixed>|null */
    private static ?array $mockQueue = null;

    public static function make(string|null $wsdl, array $options = []): SoapClient
    {
        if (self::$mockQueue !== null) {
            return new FakeSoapClient();
        }

        return new SoapClient($wsdl, $options);
    }

    /**
     * Install a queue of fake responses/exceptions. Every SOAP method call
     * made while faking is active dequeues the next item, in order,
     * regardless of which method or client it's called on.
     *
     * @param array<int, mixed> $responses
     */
    public static function fake(array $responses): void
    {
        self::$mockQueue = $responses;
    }

    public static function append(mixed ...$responses): void
    {
        self::$mockQueue = array_merge(self::$mockQueue ?? [], $responses);
    }

    public static function isFaking(): bool
    {
        return self::$mockQueue !== null;
    }

    public static function reset(): void
    {
        self::$mockQueue = null;
    }

    /**
     * @internal used by FakeSoapClient
     */
    public static function dequeue(string $method): mixed
    {
        if (empty(self::$mockQueue)) {
            throw new \RuntimeException("No fake SOAP response queued for method [$method].");
        }

        $next = array_shift(self::$mockQueue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
