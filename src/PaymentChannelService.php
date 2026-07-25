<?php

namespace MbpCoder\Payment;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Providers\Idpay;
use MbpCoder\Payment\Support\Str;

class PaymentChannelService implements IPaymentChannel
{
    private IPaymentChannel|null $gateway = null;

    /**
     * PaymentChannelService constructor.
     * @param null $name
     */
    public function __construct(string|null $name = null, string|null $token = null)
    {
        if ($name !== null && $this->isUrl($name)) {
            $this->gateway = ($name == null) ? $this->getDefaultChannel() : $this->getChannelByUrl($name, $token);
        } else {
            $this->gateway = ($name == null) ? $this->getDefaultChannel() : $this->getChannel($name, $token);
        }
    }

    private function isUrl(string $str): string
    {
        return Str::contains($str, ['//', 'http', 'https', 'www', '/']);
    }

    /**
     * Picks a channel automatically: a weighted random choice among the
     * enabled gateways, falling back to the configured static default
     * when no gateway is enabled/weighted.
     *
     * @return IPaymentChannel|null
     */
    public function getDefaultChannel(): IPaymentChannel|null
    {
        $channel = $this->selectWeightedChannel();
        if ($channel !== null) {
            return $channel;
        }
        return $this->getChannel(Config::get('channels.ipg.default'));
    }

    /**
     * Returns [name => providerConfig] for every gateway not explicitly disabled.
     *
     * @return array<string, array>
     */
    private function getEnabledProviders(): array
    {
        $providers = Config::get('channels.ipg.provider', []) ?? [];
        return array_filter($providers, fn($config) => ($config['enabled'] ?? true) == true);
    }

    /**
     * Weighted-random selection among enabled gateways: a gateway with
     * weight 10 is picked ~10% of the time relative to the total weight
     * of all enabled gateways. Purely mathematical, no persistence involved.
     *
     * @return IPaymentChannel|null
     */
    private function selectWeightedChannel(): IPaymentChannel|null
    {
        $providers = $this->getEnabledProviders();
        if (empty($providers)) {
            return null;
        }

        $weights = array_map(fn($config) => max(0, (int) ($config['weight'] ?? 1)), $providers);
        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            $name = array_rand($providers);
            return $this->getChannel($name);
        }

        $random = random_int(1, $totalWeight);
        $cumulative = 0;
        foreach ($weights as $name => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $this->getChannel($name);
            }
        }

        return null;
    }

    /**
     * @param $name
     * @param $token
     * @return IPaymentChannel|null
     */
    private function getChannel($name, string|null $token = null): IPaymentChannel|null
    {
        if ($name == 'idpay') {
            return new Idpay();
        }
        // Acronym-cased classes that don't follow the camelCase convention.
        $aliases = [
            'pep' => 'PEP',
            'pec' => 'PEC',
            'ecd' => 'ECD',
            'sadad_bnpl' => 'SadadBNPL',
        ];
        $class = $aliases[$name] ?? ucfirst(Str::camel($name));
        $className = "\\MbpCoder\\Payment\\Providers\\" . $class;
        if (class_exists($className)) {
            return new $className($token);
        }
        return null;
    }

    /**
     * @param string $url
     * @param string|null $token
     * @return IPaymentChannel|null
     */
    private function getChannelByUrl(string $url, string|null $token = null): IPaymentChannel|null
    {
        if (Str::contains($url, 'zarinp')) {
            $name = 'zarinpal';
        } else if (Str::contains($url, 'idpay')) {
            $name = 'idpay';
        } else if (Str::contains($url, 'pay.ir')) {
            $name = 'pay';
        } else {
            $str = str_replace(['https://', 'https://www.', 'http://', 'http://www.'], '', $url);
            $name = str_split($str, strpos($str, '.'))[0];
        }
        return $this->getChannel($name, $token);
    }

    /**
     * @param $callbackUrl
     */
    #[\Override]
    public function setCallbackUrl($callbackUrl): void
    {
        $this->gateway->setCallbackUrl($callbackUrl);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        return $this->gateway->initial($amount, $trackingCode, $description);
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return $this->gateway->pay($paymentToken);
    }

    #[\Override]
    public function payUrl(int|string $paymentToken): string
    {
        return $this->gateway->payUrl($paymentToken);
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        return $this->gateway->processCallback($params);
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        return $this->gateway->verify($paymentToken, $amount, $cardNumber, $trackingCode);
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name = null, $phone = null, $description = null)
    {
        if (!is_null($this->gateway)) {
            return $this->gateway->personalPaymentPage($url, $amount, $name, $phone, $description);
        }
        return $url;
    }
}
