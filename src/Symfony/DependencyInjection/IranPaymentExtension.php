<?php

namespace MbpCoder\IranPayment\Symfony\DependencyInjection;

use MbpCoder\IranPayment\PaymentChannelService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;

class IranPaymentExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store the configuration shaped exactly like the package expects:
        // channels.ipg.{default,callback_url,provider.*}
        $container->setParameter('iran_payment.config', [
            'channels' => [
                'ipg' => [
                    'default' => $config['default'],
                    'callback_url' => $config['callback_url'],
                    'provider' => $config['provider'] ?? [],
                ],
            ],
        ]);

        // Register the main service so it can be autowired/injected.
        $definition = new Definition(PaymentChannelService::class);
        $definition->setPublic(true);
        $container->setDefinition(PaymentChannelService::class, $definition);
        $container->setAlias('iran_payment', PaymentChannelService::class)->setPublic(true);
    }

    public function getAlias(): string
    {
        return 'iran_payment';
    }
}
