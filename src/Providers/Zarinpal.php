<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Support\Redirect;
use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use GuzzleHttp\Client;

class Zarinpal extends Base implements IPaymentChannel
{
    private array $codeMap = [
        '-9' => 'خطای اعتبار سنجی',
        '-10' => 'ای پی یا مرچنت كد پذیرنده صحیح نیست.',
        '-11' => 'مرچنت کد فعال نیست، پذیرنده مشکل خود را به امور مشتریان زرین‌پال ارجاع دهد.',
        '-12' => 'تلاش بیش از دفعات مجاز در یک بازه زمانی کوتاه به امور مشتریان زرین پال اطلاع دهید',
        '-15' => 'درگاه پرداخت به حالت تعلیق در آمده است، پذیرنده مشکل خود را به امور مشتریان زرین‌پال ارجاع دهد.',
        '-16' => 'سطح تایید پذیرنده پایین تر از سطح نقره ای است.',
        '-17' => 'محدودیت پذیرنده در سطح آبی',
        '-30' => 'پذیرنده اجازه دسترسی به سرویس تسویه اشتراکی شناور را ندارد.',
        '-31' => 'حساب بانکی تسویه را به پنل اضافه کنید. مقادیر وارد شده برای تسهیم درست نیست. پذیرنده جهت استفاده از خدمات سرویس تسویه اشتراکی شناور، باید حساب بانکی معتبری به پنل کاربری خود اضافه نماید.',
        '-32' => 'مبلغ وارد شده از مبلغ کل تراکنش بیشتر است.',
        '-33' => 'درصدهای وارد شده صحیح نیست.',
        '-34' => 'مبلغ وارد شده از مبلغ کل تراکنش بیشتر است.',
        '-35' => 'تعداد افراد دریافت کننده تسهیم بیش از حد مجاز است.',
        '-36' => 'حداقل مبلغ جهت تسهیم باید ۱۰۰۰۰ ریال باشد',
        '-37' => 'یک یا چند شماره شبای وارد شده برای تسهیم از سمت بانک غیر فعال است.',
        '-38' => 'خطا٬عدم تعریف صحیح شبا٬لطفا دقایقی دیگر تلاش کنید.',
        '-39' => 'خطایی رخ داده است به امور مشتریان زرین پال اطلاع دهید',
        '-40' => 'Invalid extra params, expire_in is not valid.',
        '-50' => 'مبلغ پرداخت شده با مقدار مبلغ ارسالی در متد وریفای متفاوت است.',
        '-51' => 'پرداخت ناموفق',
        '-52' => 'خطای غیر منتظره‌ای رخ داده است. پذیرنده مشکل خود را به امور مشتریان زرین‌پال ارجاع دهد.',
        '-53' => 'پرداخت متعلق به این مرچنت کد نیست.',
        '-54' => 'اتوریتی نامعتبر است.',
        '100' => 'عملیات موفق',
        '101' => 'تراکنش وریفای شده است.',
    ];

    private Client $httpClient;

    /**
     * ZarinpalPaymentChannel constructor.
     */
    public function __construct(string|null $token = null)
    {
        $this->token = $token ?? Config::get('channels.ipg.provider.zarinpal.token');
        $this->sendUrl = Config::get('channels.ipg.provider.zarinpal.send_url');
        $this->paymentUrl = Config::get('channels.ipg.provider.zarinpal.payment_url');
        $this->verifyUrl = Config::get('channels.ipg.provider.zarinpal.verify_url');

        $this->name = "Zarinpal";
        $this->httpClient = new Client([
//            'proxy' => 'socks5h://127.0.0.1:52000',
        ]);

        parent::__construct();
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $options = [];
        $options['json'] = [
            'merchant_id' => $this->token,
            'amount' => $amount,
            'callback_url' => $this->callback,
            'description' => $description,
            'currency' => 'IRR',
        ];
        $response = $this->httpClient->post($this->sendUrl, $options);
        $result = json_decode($response->getBody()->getContents(), true);

        $paymentResponse = new  PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->providerCode = $result['data']['code'];
        $paymentResponse->providerMessage = $this->codeMap[$result['data']['code']] ?? null;
        $paymentResponse->paymentToken = $result['data']['code'] == 100 ? $result['data']['authority'] : null;
        $paymentResponse->paymentStatus = $result['data']['code'] == 100 ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->paymentUrl . $paymentToken);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $this->paymentUrl . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $options = [];
        $options['json'] = [
            'merchant_id' => $this->token,
            'authority' => $paymentToken,
            'amount' => $amount,
        ];

        $response = $this->httpClient->post($this->verifyUrl, $options);
        $result = json_decode($response->getBody()->getContents(), true);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->providerCode = $result['data']['code'];
        $paymentResponse->providerMessage = $this->codeMap[$result['data']['code']] ?? null;
        $paymentResponse->referenceCode = $result['data']['ref_id'] ?? null;
        $paymentResponse->cardNumber = $result['data']['card_pan'] ?? null;
        $paymentResponse->paymentStatus = $result['data']['code'] == 100 ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;

        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentStatus = ($params['Status'] ?? null) === 'OK' ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        $paymentResponse->paymentToken = $params['Authority'] ?? null;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url . '?amount=' . ($amount * 10);
    }
}
