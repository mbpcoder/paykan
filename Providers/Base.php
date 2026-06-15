<?php

namespace App\Channels\PaymentChannels\Providers;

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
        $this->callback = config('channels.ipg.callback_url');
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
