<?php

namespace MbpCoder\IranPayment\Providers;

use MbpCoder\IranPayment\IPaymentChannel;
use MbpCoder\IranPayment\Models\PaymentResponse;
use Exception;

class Sep extends Base implements IPaymentChannel
{
    private array $codeMap = [
        '1' => 200,
        '-1' => 1010, //ارسال api الزامی می باشد
        '-3' => 1014, //ارسال amount ( مبلغ تراکنش ) الزامی می باشد
        '-4' => 1015, //amount ( مبلغ تراکنش )باید به صورت عددی باشد
        '-6' => 1016, //amount نباید کمتر از 1000 باشد
        '-7' => 1017, //ارسال redirect الزامی می باشد
        '-8' => 1011, //درگاه پرداختی با api ارسالی یافت نشد و یا غیر فعال می باشد
        '-9' => 1012, //فروشنده غیر فعال می باشد
        '-10' => 1013, //تراکنش با خطا مواجه شد
        '-11' => 1014, //ارسال api الزامی می باشد
        '-12' => 1015, //ارسال transId الزامی می باشد
        '-13' => 1016, //درگاه پرداختی با api ارسالی یافت نشد و یا غیر فعال می باشد
        '-14' => 1017, //فروشنده غیر فعال می باشد
        '-15' => 1018, //تراکنش با خطا مواجه شده است
        '-16' => 1019, //تراکنش با خطا مواجه شده است
        '-17' => 1020, //تراکنش با خطا مواجه شده است
        '-18' => 1021, //تراکنش با خطا مواجه شده است
    ];

    /**
     * PayPaymentChannel constructor.
     */
    public function __construct()
    {
        if (!extension_loaded('soap')) {
            throw new Exception('soap Extension is not loaded');
            // Do things
        }

        parent::__construct();

        $this->token = \MbpCoder\IranPayment\Config\Config::get('channels.ipg.provider.sep.token');
        $this->sendUrl = \MbpCoder\IranPayment\Config\Config::get('channels.ipg.provider.sep.send_url');
        $this->paymentUrl = \MbpCoder\IranPayment\Config\Config::get('channels.ipg.provider.sep.payment_url');
        $this->verifyUrl = \MbpCoder\IranPayment\Config\Config::get('channels.ipg.provider.sep.verify_url');
        if (\MbpCoder\IranPayment\Config\Config::get('channels.ipg.provider.sep.callback_url')) {
            $this->callback = \MbpCoder\IranPayment\Config\Config::get('channels.ipg.provider.sep.callback_url');
        }

        $this->name = "Sep";
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $client = new \SoapClient($this->sendUrl);
        $result = null;
        $params = [
            'MID' => $this->token,
            'ResNum' => $description,
            'Amount' => $amount
        ];

        $result = $client->__soapCall('RequestToken', $params);

        return [
            'status' => $result < 0 ? $this->codeMap[$result] : $this->codeMap['1'],
            'authority' => strlen((string)$result) > 4 ? $result : null
        ];

    }

    public function pay($paymentToken)
    {
        return "<html><body><script>var form=document.createElement('FORM'),token=document.createElement('INPUT'),url=document.createElement('INPUT');token.name='Token';token.value='{$paymentToken}';url.name='RedirectUrl';url.value='{$this->callback}';form.method='POST';form.action='{$this->paymentUrl}';form.appendChild(token);form.appendChild(url);document.body.appendChild(form);form.submit();</script></body></html>";
    }

    public function payUrl(string|int $paymentToken): string
    {
        return $paymentToken;
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $client = new \SoapClient($this->verifyUrl);
        $params = [
            'RefNum' => $paymentToken,
            'MID' => $this->token
        ];

        $result = $client->__soapCall('verifyTransaction', $params);

        return [
            'status' => $result < 0 ? $this->codeMap[$result] : $this->codeMap['1'],
            'amount' => $result > 0 ? $result : null
        ];
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    public function processCallback(array $params): PaymentResponse
    {
        // TODO: Implement processCallback() method.
    }
}
