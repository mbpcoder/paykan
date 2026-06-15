<?php

namespace MbpCoder\IranPayment\Symfony;

use MbpCoder\IranPayment\Config\ArrayConfigRepository;
use MbpCoder\IranPayment\Config\Config;
use MbpCoder\IranPayment\Support\Redirect;
use MbpCoder\IranPayment\Symfony\DependencyInjection\IranPaymentExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class IranPaymentBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new IranPaymentExtension();
    }

    public function boot(): void
    {
        // Wire the package's static config accessor to the resolved bundle config.
        $config = $this->container->getParameter('iran_payment.config');
        Config::setRepository(new ArrayConfigRepository($config));

        // Gateway redirects return a Symfony RedirectResponse.
        Redirect::setHandler(fn (string $url) => new RedirectResponse($url));
    }
}
