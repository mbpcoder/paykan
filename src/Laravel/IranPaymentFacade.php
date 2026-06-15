<?php

namespace MbpCoder\IranPayment\Laravel;

use Illuminate\Support\Facades\Facade;
use MbpCoder\IranPayment\PaymentChannelService;

/**
 * @method static \MbpCoder\IranPayment\Models\PaymentResponse initial(int $amount, string|int $trackingCode, ?string $description = null)
 * @method static mixed pay(string|int $paymentToken)
 * @method static string payUrl(string|int $paymentToken)
 * @method static \MbpCoder\IranPayment\Models\PaymentResponse verify(string|int $paymentToken, int $amount, ?string $cardNumber = null, string|int|null $trackingCode = null)
 * @method static \MbpCoder\IranPayment\Models\PaymentResponse processCallback(array $params)
 * @method static void setCallbackUrl(string $callbackUrl)
 *
 * @see PaymentChannelService
 */
class IranPaymentFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'iran-payment';
    }
}
