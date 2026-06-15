<?php

namespace MbpCoder\Payment\Laravel;

use Illuminate\Support\Facades\Facade;
use MbpCoder\Payment\PaymentChannelService;

/**
 * @method static \MbpCoder\Payment\Models\PaymentResponse initial(int $amount, string|int $trackingCode, ?string $description = null)
 * @method static mixed pay(string|int $paymentToken)
 * @method static string payUrl(string|int $paymentToken)
 * @method static \MbpCoder\Payment\Models\PaymentResponse verify(string|int $paymentToken, int $amount, ?string $cardNumber = null, string|int|null $trackingCode = null)
 * @method static \MbpCoder\Payment\Models\PaymentResponse processCallback(array $params)
 * @method static void setCallbackUrl(string $callbackUrl)
 *
 * @see PaymentChannelService
 */
class PaymentFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'payment';
    }
}
