<?php

namespace MbpCoder\Payment\Support;

/**
 * Framework-agnostic redirect helper.
 *
 * The package's gateway "pay" methods need to send the user's browser to the
 * gateway. Because the package must not depend on any single framework, the
 * actual redirect mechanism is pluggable:
 *
 *  - Plain PHP: a "Location" header is emitted (the default handler).
 *  - Laravel: the service provider sets a handler that calls redirect($url).
 *  - Symfony: the bundle sets a handler returning a RedirectResponse.
 *
 * Whatever the configured handler returns is returned to the caller, so each
 * framework receives the native redirect object it expects.
 */
final class Redirect
{
    /** @var callable|null */
    private static $handler = null;

    /**
     * Register the framework-specific redirect handler.
     *
     * @param callable $handler function(string $url): mixed
     */
    public static function setHandler(callable $handler): void
    {
        self::$handler = $handler;
    }

    /**
     * Redirect to the given URL using the configured handler.
     *
     * @return mixed
     */
    public static function to(string $url): mixed
    {
        if (self::$handler !== null) {
            return (self::$handler)($url);
        }

        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
        }
        return $url;
    }
}
