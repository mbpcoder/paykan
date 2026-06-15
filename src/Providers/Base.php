<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Config\Config;

abstract class Base
{
    protected $name;
    protected $callback;
    protected $token;
    protected $sendUrl;
    protected $paymentUrl;
    protected $verifyUrl;

    /**
     * PaymentChannelBase constructor.
     */
    public function __construct()
    {
        //set the default callback url
        $this->callback = Config::get('channels.ipg.callback_url');
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $callbackUrl
     */
    public function setCallbackUrl(string $callbackUrl): void
    {
        $this->callback = $callbackUrl;
    }
}
