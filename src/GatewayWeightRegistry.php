<?php

namespace MbpCoder\Payment;

/**
 * In-memory runtime overrides for gateway `enabled`/`weight`, on top of
 * whatever `config/channels.php` declares. No persistence, no database:
 * values live only in process memory and reset when the process restarts,
 * so on a traditional PHP-FPM/CLI setup they apply for the current request
 * only. To make an override outlive a single request, call these methods
 * on every boot (e.g. from your own persistence layer, cache, or a long
 * running worker such as Octane/RoadRunner/Swoole).
 */
class GatewayWeightRegistry
{
    /** @var array<string, bool> */
    private static array $enabledOverrides = [];

    /** @var array<string, int> */
    private static array $weightOverrides = [];

    public static function setEnabled(string $name, bool $enabled): void
    {
        self::$enabledOverrides[$name] = $enabled;
    }

    public static function enable(string $name): void
    {
        self::setEnabled($name, true);
    }

    public static function disable(string $name): void
    {
        self::setEnabled($name, false);
    }

    public static function setWeight(string $name, int $weight): void
    {
        self::$weightOverrides[$name] = max(0, $weight);
    }

    /**
     * Bulk-set weights, e.g. GatewayWeightRegistry::setWeights(['zarinpal' => 10, 'pay' => 90]).
     *
     * @param array<string, int> $weights
     */
    public static function setWeights(array $weights): void
    {
        foreach ($weights as $name => $weight) {
            self::setWeight($name, $weight);
        }
    }

    public static function isEnabled(string $name, bool $default): bool
    {
        return self::$enabledOverrides[$name] ?? $default;
    }

    public static function getWeight(string $name, int $default): int
    {
        return self::$weightOverrides[$name] ?? $default;
    }

    public static function clear(string $name): void
    {
        unset(self::$enabledOverrides[$name], self::$weightOverrides[$name]);
    }

    public static function reset(): void
    {
        self::$enabledOverrides = [];
        self::$weightOverrides = [];
    }
}
