# PHP Payment

[فارسی](README.fa.md)

A framework-agnostic PHP package for working with payment gateways
(Zarinpal, IDPay, Pay.ir, Jibit, Sep, PayPing, Bahamta, PayStar) with
first-class integrations for **Laravel** and **Symfony**, and a plain-PHP
fallback.

## Installation

```bash
composer require mbpcoder/php-payment
```

Requires PHP >= 8.4.

## Architecture

The core (`MbpCoder\Payment\PaymentChannelService` and the gateway
providers) has no framework dependency. Two small seams make it portable:

- **Config** – `MbpCoder\Payment\Config\Config` is a static accessor backed
  by a swappable `ConfigRepositoryInterface`. Each integration points it at the
  framework's own config.
- **Redirect** – `MbpCoder\Payment\Support\Redirect` resolves gateway
  redirects through a pluggable handler so each framework gets its native
  redirect object.

## Usage (any framework)

```php
use MbpCoder\Payment\PaymentChannelService;

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

Auto-discovery registers the service provider and the `Payment` facade.
Publish the config:

```bash
php artisan vendor:publish --tag=payment-config
```

This creates `config/channels.php`. Set your gateway credentials there (or via
the `.env` keys it references), then:

```php
use MbpCoder\Payment\PaymentChannelService;

$payment = app(PaymentChannelService::class);   // default gateway
$payment = new PaymentChannelService('zarinpal'); // a specific gateway

// or via the facade
Payment::initial(10000, 'order-42');
```

`pay()` returns a Laravel redirect, so you can `return` it from a controller.

## Symfony

Register the bundle:

```php
// config/bundles.php
return [
    // ...
    MbpCoder\Payment\Symfony\PaymentBundle::class => ['all' => true],
];
```

Configure it:

```yaml
# config/packages/payment.yaml
payment:
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
use MbpCoder\Payment\PaymentChannelService;

public function checkout(PaymentChannelService $payment) {
    $response = $payment->initial(10000, 'order-42');
    return $payment->pay($response->paymentToken); // Symfony RedirectResponse
}
```

`pay()` returns a `Symfony\Component\HttpFoundation\RedirectResponse`.

## Plain PHP

Provide configuration manually:

```php
use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Config\ArrayConfigRepository;

Config::setRepository(new ArrayConfigRepository(
    require __DIR__ . '/config/channels.php'
));
```

`pay()` then emits a `Location` header by default. Register a custom redirect
handler with `MbpCoder\Payment\Support\Redirect::setHandler(...)` if needed.

## Supported gateways

Original: Zarinpal, IDPay, Pay.ir, Jibit, Sep, PayPing, Bahamta, PayStar.

Ported from [farayaz/larapay](https://github.com/farayaz/larapay): Vandar, Zibal,
BehPardakht (Mellat), AsanPardakht, Azkivam, Bitpay, Digipay, ECD, FanavaCard,
IranDargah, IranKish, IsipaymentSamin, Keepa, MehrIran, NextPay, Omidpay, PEC,
PEP (Parsian), PardakhtNovin, Polam, RefahBeta, Sadad, SadadBNPL, Sepal,
Sepehrpay, Shepa, SnappPay, TabaPay, TejaratBajet.

Configure each under `channels.ipg.provider.<name>` (see `config/channels.php`).
Instantiate via `new PaymentChannelService('<name>')`, e.g. `'vandar'`, `'zibal'`,
`'beh_pardakht'`, `'pardakht_novin'`.

### Enabled gateways and automatic weighted distribution

Every provider entry has an `enabled` flag and a `weight`:

```php
'zarinpal' => [
    'enabled' => env('ZARINPAL_ENABLED', true),
    'weight' => env('ZARINPAL_WEIGHT', 1),
    // ...
],
```

When you build `new PaymentChannelService()` (or resolve it from the container)
**without passing a gateway name**, one of the *enabled* gateways is picked
automatically with a weighted random choice — no gateway name needs to reach
the manager/service class at all. The chance of a gateway being picked is
proportional to its weight versus the total weight of all enabled gateways:
with `zarinpal` weight `10` and `pay` weight `90` (both enabled, total `100`),
`zarinpal` is selected on ~10% of calls and `pay` on ~90%. This is computed on
the fly with `random_int()` — no database or stored state involved. Setting
`enabled` to `false` removes a gateway from the pool entirely; if no gateway
is enabled, the static `channels.ipg.default` name is used as a fallback.

### Changing enabled/weight at runtime

`MbpCoder\Payment\GatewayWeightRegistry` lets you override `enabled`/`weight`
at runtime, on top of `config/channels.php`, without touching a database:

```php
use MbpCoder\Payment\GatewayWeightRegistry;

GatewayWeightRegistry::disable('pay');
GatewayWeightRegistry::enable('zarinpal');
GatewayWeightRegistry::setWeight('zarinpal', 10);
GatewayWeightRegistry::setWeights(['zarinpal' => 10, 'jibit' => 90]); // bulk

// Every subsequent `new PaymentChannelService()` immediately reflects this.
```

These overrides live only in process memory (a static array) — there is
still no database or file write involved, it's just a runtime layer over the
config. On classic PHP-FPM/CLI requests that means the override only applies
for the current request/process; call it on every boot (from your own admin
settings, cache, etc.) if you need it to persist across requests, or set it
once if you're running a long-lived worker (Octane, RoadRunner, Swoole).
Call `GatewayWeightRegistry::clear('<name>')` or `::reset()` to drop
overrides and fall back to the static config again.

> Notes: SOAP gateways (BehPardakht, PEC, Sep) require the PHP `ext-soap`
> extension. A few credit/OTP gateways (e.g. TejaratBajet, PardakhtNovin,
> SadadBNPL) need callback/OTP data that the generic interface does not carry —
> see the class docblocks for how those values map onto `verify()`'s arguments.

## License

MIT
