<?php

namespace MbpCoder\IranPayment\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MbpCoder\IranPayment\Config\Config;
use MbpCoder\IranPayment\Config\ConfigRepositoryInterface;
use MbpCoder\IranPayment\PaymentChannelService;
use MbpCoder\IranPayment\Support\Redirect;

class IranPaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'channels');

        // Bridge the package's static config accessor to Laravel's config repo.
        Config::setRepository($this->makeConfigBridge());

        // Use Laravel's redirector for gateway redirects.
        Redirect::setHandler(fn (string $url) => redirect()->away($url));

        $this->app->bind(PaymentChannelService::class, fn () => new PaymentChannelService());
        $this->app->alias(PaymentChannelService::class, 'iran-payment');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => $this->app->configPath('channels.php'),
            ], 'iran-payment-config');
        }
    }

    private function configPath(): string
    {
        return __DIR__ . '/../../config/channels.php';
    }

    private function makeConfigBridge(): ConfigRepositoryInterface
    {
        $laravelConfig = $this->app->make('config');

        return new class($laravelConfig) implements ConfigRepositoryInterface {
            public function __construct(private $config)
            {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->config->get($key, $default);
            }
        };
    }
}
