<?php

namespace MbpCoder\Payment\Symfony;

use MbpCoder\Payment\Config\ArrayConfigRepository;
use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Support\Redirect;
use MbpCoder\Payment\Symfony\DependencyInjection\PaymentExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PaymentBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new PaymentExtension();
    }

    public function boot(): void
    {
        // Wire the package's static config accessor to the resolved bundle config.
        $config = $this->container->getParameter('payment.config');
        Config::setRepository(new ArrayConfigRepository($config));

        // Gateway redirects return a Symfony RedirectResponse.
        Redirect::setHandler(fn (string $url) => new RedirectResponse($url));
    }
}
