<?php

namespace MbpCoder\IranPayment\Config;

/**
 * Static accessor for package configuration.
 *
 * Framework integrations (Laravel service provider, Symfony bundle) or
 * plain-PHP consumers set a {@see ConfigRepositoryInterface} here once during
 * bootstrap. The rest of the package reads its settings through this accessor,
 * keeping providers free of any framework coupling.
 */
final class Config
{
    private static ?ConfigRepositoryInterface $repository = null;

    public static function setRepository(ConfigRepositoryInterface $repository): void
    {
        self::$repository = $repository;
    }

    public static function getRepository(): ConfigRepositoryInterface
    {
        if (self::$repository === null) {
            self::$repository = new ArrayConfigRepository();
        }
        return self::$repository;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getRepository()->get($key, $default);
    }
}
