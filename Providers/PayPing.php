<?php

namespace App\Channels\PaymentChannels\Providers;

use App\Channels\PaymentChannels\IPaymentChannel;
use App\Channels\PaymentChannels\Models\PaymentResponse;
use App\Channels\PaymentChannels\Models\PaymentStatus;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class PayPing extends Base implements IPaymentChannel
{
    private Client $client;

    public function __construct()
    {
        parent::__construct();

        $this->token = config('channels.ipg.provider.PayPing.token');
        $this->sendUrl = config('channels.ipg.provider.PayPing.send_url');
        $this->verifyUrl = config('channels.ipg.provider.PayPing.verify_url');

        $this->name = "PayPing";

        $clientConstructorParameters = [
            'verify' => false,
        ];
        $this->client = new Client($clientConstructorParameters);
    }


    public function initial(int $amount, int|string $trackingCode, string|null $description = null): PaymentResponse
    {
        // TODO: Implement initial() method.
    }

    public function pay(int|string $paymentToken)
    {
        // TODO: Implement pay() method.
    }

    public function payUrl(int|string $paymentToken): string
    {
        // TODO: Implement payUrl() method.
    }

    public function verify(int|string $paymentToken, int $amount, ?string $cardNumber = null, int|string|null $trackingCode = null): PaymentResponse
    {
        // TODO: Implement verify() method.
    }

    public function processCallback(array $params): PaymentResponse
    {
        // TODO: Implement processCallback() method.
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        // TODO: Implement personalPaymentPage() method.
    }
}
