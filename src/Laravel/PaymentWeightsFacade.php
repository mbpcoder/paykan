<?php

namespace MbpCoder\Payment\Laravel;

use Illuminate\Support\Facades\Facade;
use MbpCoder\Payment\GatewayWeightRegistry;

/**
 * @method static void setEnabled(string $name, bool $enabled)
 * @method static void enable(string $name)
 * @method static void disable(string $name)
 * @method static void setWeight(string $name, int $weight)
 * @method static void setWeights(array $weights)
 * @method static bool isEnabled(string $name, bool $default)
 * @method static int getWeight(string $name, int $default)
 * @method static void clear(string $name)
 * @method static void reset()
 *
 * @see GatewayWeightRegistry
 */
class PaymentWeightsFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'payment.weights';
    }
}
