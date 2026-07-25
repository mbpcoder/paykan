<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Support\Redirect;
use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\Http\ClientFactory;
use GuzzleHttp\Client;

class Bahamta extends Base implements IPaymentChannel
{
    private $client;

    /**
     * PayPaymentChannel constructor.
     */
    public function __construct(string|null $token = null)
    {
        parent::__construct();

        $this->token = Config::get('channels.ipg.provider.bahamta.token');
        $this->sendUrl = Config::get('channels.ipg.provider.bahamta.send_url');
        $this->verifyUrl = Config::get('channels.ipg.provider.bahamta.verify_url');

        $this->name = "bahamta";

        $clientConstructorParameters = [
            'verify' => false,
        ];
        $this->client = ClientFactory::make($clientConstructorParameters);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $this->sendUrl .= '?api_key=' . $this->token . '&callback_url=' . $this->callback . '&reference=' . $trackingCode . '&amount_irr=' . $amount;

        $response = $this->client->get($this->sendUrl);
        $result = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;

        if ($result['ok'] === true) {
            $paymentResponse->paymentToken = $result['result']['payment_url'] ?? null;
            $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        } else {
            $paymentResponse->providerMessage = $result['error'];
            $paymentResponse->paymentStatus = PaymentStatus::FAILED;
        }
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return Redirect::to($paymentToken);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $this->verifyUrl .= '?api_key=' . $this->token . '&reference=' . $paymentToken . '&amount_irr=' . $amount;

        $response = $this->client->get($this->verifyUrl);
        $result = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;

        if ($result['ok'] === true && $result['result']['state'] === 'paid') {
            $paymentResponse->paymentToken = $result['result']['pay_ref'] ?? null;
            $paymentResponse->cardNumber = $result['result']['pay_pan'] ?? null;
            $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        } else {
            $paymentResponse->providerMessage = $result['error'];
            $paymentResponse->paymentStatus = PaymentStatus::FAILED;
        }
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->trackingCode = $params['reference'];
        if ($params['state'] === 'error') {
            $paymentResponse->providerMessage = $params['error_message'];
            $paymentResponse->providerCode = $params['error_key'];
            $paymentResponse->paymentStatus = PaymentStatus::FAILED;
        } else {
            $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        }

        return $paymentResponse;
    }
}
