# PHP Iran Payment

A framework-agnostic PHP package for working with Iranian payment gateways
(Zarinpal, IDPay, Pay.ir, Jibit, Sep, PayPing, Bahamta, PayStar) with
first-class integrations for **Laravel** and **Symfony**, and a plain-PHP
fallback.

## Installation

```bash
composer require mbpcoder/php-iran-payment
```

Requires PHP >= 8.1.

## Architecture

The core (`MbpCoder\IranPayment\PaymentChannelService` and the gateway
providers) has no framework dependency. Two small seams make it portable:

- **Config** – `MbpCoder\IranPayment\Config\Config` is a static accessor backed
  by a swappable `ConfigRepositoryInterface`. Each integration points it at the
  framework's own config.
- **Redirect** – `MbpCoder\IranPayment\Support\Redirect` resolves gateway
  redirects through a pluggable handler so each framework gets its native
  redirect object.

## Usage (any framework)

```php
use MbpCoder\IranPayment\PaymentChannelService;

$payment = new PaymentChannelService('zarinpal'); // or null for the default
$response = $payment->initial(amount: 10000, trackingCode: 'order-42');

if ($response->isSuccess()) {
    return $payment->pay($response->paymentToken); // redirects to the gateway
}
```

On the callback route:

```php
$payment = new PaymentChannelService('zarinpal');
$result  = $payment->verify($paymentToken, 10000);
$result->isSuccess(); // true / false
```

## Laravel

Auto-discovery registers the service provider and the `IranPayment` facade.
Publish the config:

```bash
php artisan vendor:publish --tag=iran-payment-config
```

This creates `config/channels.php`. Set your gateway credentials there (or via
the `.env` keys it references), then:

```php
use MbpCoder\IranPayment\PaymentChannelService;

$payment = app(PaymentChannelService::class);   // default gateway
$payment = new PaymentChannelService('zarinpal'); // a specific gateway

// or via the facade
IranPayment::initial(10000, 'order-42');
```

`pay()` returns a Laravel redirect, so you can `return` it from a controller.

## Symfony

Register the bundle:

```php
// config/bundles.php
return [
    // ...
    MbpCoder\IranPayment\Symfony\IranPaymentBundle::class => ['all' => true],
];
```

Configure it:

```yaml
# config/packages/iran_payment.yaml
iran_payment:
    default: zarinpal
    callback_url: 'https://example.com/payment/callback'
    provider:
        zarinpal:
            token: '%env(ZARINPAL_TOKEN)%'
            send_url: 'https://api.zarinpal.com/pg/v4/payment/request.json'
            payment_url: 'https://www.zarinpal.com/pg/StartPay/'
            verify_url: 'https://api.zarinpal.com/pg/v4/payment/verify.json'
```

Inject the service:

```php
use MbpCoder\IranPayment\PaymentChannelService;

public function checkout(PaymentChannelService $payment) {
    $response = $payment->initial(10000, 'order-42');
    return $payment->pay($response->paymentToken); // Symfony RedirectResponse
}
```

`pay()` returns a `Symfony\Component\HttpFoundation\RedirectResponse`.

## Plain PHP

Provide configuration manually:

```php
use MbpCoder\IranPayment\Config\Config;
use MbpCoder\IranPayment\Config\ArrayConfigRepository;

Config::setRepository(new ArrayConfigRepository(
    require __DIR__ . '/config/channels.php'
));
```

`pay()` then emits a `Location` header by default. Register a custom redirect
handler with `MbpCoder\IranPayment\Support\Redirect::setHandler(...)` if needed.

## Supported gateways

Zarinpal, IDPay, Pay.ir, Jibit, Sep, PayPing, Bahamta, PayStar.

## License

MIT
