<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Support\Redirect;
use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Pay extends Base implements IPaymentChannel
{
    private $client;
    private array $codeMap = [
        'send' => [
            '1' => 200,
            '-1' => 1010, //ارسال api الزامی می باشد
            '-2' => 1014, //ارسال amount ( مبلغ تراکنش ) الزامی می باشد
            '-3' => 1015, //amount ( مبلغ تراکنش )باید به صورت عددی باشد
            '-4' => 1016, //amount نباید کمتر از 1000 باشد
            '-5' => 1017, //ارسال redirect الزامی می باشد
            '-6' => 1011, //درگاه پرداختی با api ارسالی یافت نشد و یا غیر فعال می باشد
            '-7' => 1012, //فروشنده غیر فعال می باشد
            'failed' => 1013, //تراکنش با خطا مواجه شد
        ],
        'verify' => [
            '1' => 200,
            '-1' => 1010, //ارسال api الزامی می باشد
            '-2' => 1018, //ارسال transId الزامی می باشد
            '-3' => 1011, //درگاه پرداختی با api ارسالی یافت نشد و یا غیر فعال می باشد
            '-4' => 1012, //فروشنده غیر فعال می باشد
            '-5' => 1013, //تراکنش با خطا مواجه شده است
        ]
    ];

    /**
     * PayPaymentChannel constructor.
     */
    public function __construct()
    {
        $clientConstructorParameters = [];
        parent::__construct();

        $this->token = Config::get('channels.ipg.provider.pay.token');
        $this->sendUrl = Config::get('channels.ipg.provider.pay.send_url');
        $this->paymentUrl = Config::get('channels.ipg.provider.pay.payment_url');
        $this->verifyUrl = Config::get('channels.ipg.provider.pay.verify_url');

        $this->name = "Pay";

        $bindInterface = Config::get('channels.ipg.provider.pay.bind_interface');
        if ($bindInterface) {
            $clientConstructorParameters['curl'] = [
                CURLOPT_INTERFACE => $bindInterface,
            ];
        }
        $this->client = new Client($clientConstructorParameters);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $result = null;
        $options = [];
        $options['form_params'] = [
            'api' => $this->token,
            'amount' => $amount,
            'redirect' => $this->callback,
            'factorNumber' => $description,
        ];

        $response = $this->client->request('POST', $this->sendUrl, $options);
        $result = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'status' => ($result['status'] == 1) ? $this->codeMap['send'][$result['status']] : $this->codeMap['send'][$result['errorCode']],
            'authority' => ($result['status'] == 1) ? $result['token'] : null,
        ];

    }

    /**
     * @param string $paymentToken
     * @return mixed
     */
    public function pay($paymentToken)
    {
        return Redirect::to($this->paymentUrl . $paymentToken);
    }

    public function payUrl(string|int $paymentToken): string
    {
        return $paymentToken;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        return $paymentResponse;
    }

    /**
     * @param int|string $paymentToken
     * @param int $amount
     * @param string|null $cardNumber
     * @param string|int|null $trackingCode
     * @return array
     * @throws GuzzleException
     */
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $result = null;
        $options = [];
        $options['form_params'] = [
            'api' => $this->token,
            'token' => $paymentToken,
        ];

        $response = $this->client->request('POST', $this->verifyUrl, $options);
        $result = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'status' => $result['status'] == 1 ? $this->codeMap['verify'][$result['status']] : $this->codeMap['verify'][$result['errorCode']],
            'refId' => $result['status'] == 1 ? time() : null,
        ];
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

}
