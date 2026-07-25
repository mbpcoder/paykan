<?php

namespace MbpCoder\Payment\Laravel;

use MbpCoder\Payment\GatewayWeightRegistry;

/**
 * Thin instance wrapper around GatewayWeightRegistry so it can be resolved
 * from the container and proxied through the PaymentWeights facade
 * (Laravel facades call instance methods on the resolved object).
 */
class GatewayWeightManager
{
    public function setEnabled(string $name, bool $enabled): void
    {
        GatewayWeightRegistry::setEnabled($name, $enabled);
    }

    public function enable(string $name): void
    {
        GatewayWeightRegistry::enable($name);
    }

    public function disable(string $name): void
    {
        GatewayWeightRegistry::disable($name);
    }

    public function setWeight(string $name, int $weight): void
    {
        GatewayWeightRegistry::setWeight($name, $weight);
    }

    /**
     * @param array<string, int> $weights
     */
    public function setWeights(array $weights): void
    {
        GatewayWeightRegistry::setWeights($weights);
    }

    public function isEnabled(string $name, bool $default): bool
    {
        return GatewayWeightRegistry::isEnabled($name, $default);
    }

    public function getWeight(string $name, int $default): int
    {
        return GatewayWeightRegistry::getWeight($name, $default);
    }

    public function clear(string $name): void
    {
        GatewayWeightRegistry::clear($name);
    }

    public function reset(): void
    {
        GatewayWeightRegistry::reset();
    }
}
