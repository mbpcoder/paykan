<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Exceptions\GatewayException;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\FormRedirect;
use MbpCoder\Payment\Support\Http\Http;

/**
 * IranKish (https://ikc.shaparak.ir/) — ported from farayaz/larapay.
 * Uses RSA/AES tokenization and a POST-form redirect.
 */
class IranKish extends Base implements IPaymentChannel
{
    private string $url = 'https://ikc.shaparak.ir/';

    private array $statuses = [
        3 => 'از انجام تراکنش صرف نظر شد',
        5 => 'پذیرنده فروشگاهی نامعتبر است',
        64 => 'مبلغ تراکنش نادرست است،جمع مبالغ تقسیم وجوه برابر مبلغ کل تراکنش نمی باشد',
        94 => 'تراکنش تکراری است',
        25 => 'تراکنش اصلی یافت نشد',
        77 => 'روز مالی تراکنش نا معتبر است',
        97 => 'کد تولید کد اعتبار سنجی نا معتبر است',
        30 => 'فرمت پیام نادرست است',
        86 => 'شتاب در حال Sign Off  است',
        55 => 'رمز کارت نادرست است',
        40 => 'عمل درخواستی پشتیبانی نمی شود',
        57 => 'انجام تراکنش مورد درخواست توسط پایانه انجام دهنده مجاز نمی باشد',
        58 => 'انجام تراکنش مورد درخواست توسط پایانه انجام دهنده مجاز نمی باشد',
        63 => 'تمهیدات امنیتی نقض گردیده است',
        96 => 'قوانین سامانه نقض گردیده است ، خطای داخلی سامانه',
        2 => 'تراکنش قبلا برگشت شده است',
        54 => 'تاریخ انقضا کارت سررسید شده است',
        62 => 'کارت محدود شده است',
        75 => 'تعداد دفعات ورود رمز اشتباه از حد مجاز فراتر رفته است',
        14 => 'اطلاعات کارت صحیح نمی باشد',
        51 => 'موجودی حساب کافی نمی باشد',
        56 => 'اطلاعات کارت یافت نشد',
        61 => 'مبلغ تراکنش بیش از حد مجاز است',
        65 => 'تعداد دفعات انجام تراکنش بیش از حد مجاز است',
        78 => 'کارت فعال نیست',
        79 => 'حساب متصل به کارت بسته یا دارای اشکال است',
        42 => 'کارت یا حساب مبدا (مقصد) در وضعیت پذیرش نمی باشد',
        31 => 'عدم تطابق کد ملی خریدار با دارنده کارت',
        98 => 'سقف استفاده از رمز دوم ایستا به پایان رسیده است',
        901 => 'درخواست نا معتبر است (Tokenization)',
        902 => 'پارامترهای اضافی درخواست نامعتبر می باشد (Tokenization)',
        903 => 'شناسه پرداخت نامعتبر می باشد (Tokenization)',
        904 => 'اطلاعات مرتبط با قبض نا معتبر می باشد (Tokenization)',
        905 => 'شناسه درخواست نامعتبر می باشد (Tokenization)',
        906 => 'درخواست تاریخ گذشته است (Tokenization)',
        907 => 'آدرس بازگشت نتیجه پرداخت نامعتبر می باشد (Tokenization',
        909 => 'پذیرنده نامعتبر می باشد(Tokenization)',
        910 => 'پارامترهای مورد انتظار پرداخت تسهیمی تامین نگردیده است(Tokenization)',
        911 => 'پارامترهای مورد انتظار پرداخت تسهیمی نا معتبر یا دارای اشکال می باشد(Tokenization)',
        912 => 'تراکنش درخواستی برای پذیرنده فعال نیست (Tokenization)',
        913 => 'تراکنش تسهیم برای پذیرنده فعال نیست (Tokenization)',
        914 => 'آدرس آی پی دریافتی درخواست نا معتبر می باشد',
        915 => 'شماره پایانه نامعتبر می باشد (Tokenization)',
        916 => 'شماره پذیرنده نا معتبر می باشد (Tokenization)',
        917 => 'نوع تراکنش اعلام شده در خواست نا معتبر می باشد (Tokenization)',
        918 => 'پذیرنده فعال نیست(Tokenization)',
        919 => 'مبالغ تسهیمی ارائه شده با توجه به قوانین حاکم بر وضعیت تسهیم پذیرنده ، نا معتبر است (Tokenization)',
        920 => 'شناسه نشانه نامعتبر می باشد',
        921 => 'شناسه نشانه نامعتبر و یا منقضی شده است',
        922 => 'نقض امنیت درخواست (Tokenization)',
        923 => 'ارسال شناسه پرداخت در تراکنش قبض مجاز نیست(Tokenization)',
        928 => 'مبلغ مبادله شده نا معتبر می باشد(Tokenization)',
        929 => 'شناسه پرداخت ارائه شده با توجه به الگوریتم متناظر نا معتبر می باشد(Tokenization)',
        930 => 'کد ملی ارائه شده نا معتبر می باشد(Tokenization)',

        'token-mismatch' => 'مغایرت توکن بازگشتی',
        'amount-mismatch' => 'مغایرت مبلغ پرداختی',
        'failed' => 'ناموفق',
        'id-mismatch' => 'عدم تطبیق شناسه بازگشتی',
        'connection-exception' => 'خطا ارتباطی با سرویس دهنده',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'IranKish';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.iran_kish.' . $key, $default);
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $url = $this->url . 'api/v3/tokenization/make';
        $encrypted = $this->encrypt(
            $this->cfg('pubKey'),
            $this->cfg('terminalId'),
            $this->cfg('password'),
            $amount
        );
        $params = [
            'request' => [
                'acceptorId' => $this->cfg('acceptorId'),
                'amount' => $amount,
                'billInfo' => null,
                'paymentId' => (string) $trackingCode,
                'requestId' => uniqid(),
                'requestTimestamp' => time(),
                'revertUri' => $this->callback,
                'terminalId' => $this->cfg('terminalId'),
                'transactionType' => 'Purchase',
            ],
            'authenticationEnvelope' => $encrypted,
        ];
        $result = $this->request($url, $params);

        if ($result['responseCode'] != '00') {
            throw new GatewayException($result['description']);
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['result']['token'];
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), ['tokenIdentity' => $paymentToken]);
    }

    public function payUrl(string|int $paymentToken): string
    {
        return $this->url . 'iuiv3/IPG/Index/';
    }

    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $url = $this->url . 'api/v3/confirmation/purchase';
        $data = [
            'terminalId' => $this->cfg('terminalId'),
            'retrievalReferenceNumber' => $this->retrievalReferenceNumber,
            'systemTraceAuditNumber' => $this->systemTraceAuditNumber,
            'tokenIdentity' => $paymentToken,
        ];
        $result = $this->request($url, $data);

        if ($result['result']['responseCode'] != '00') {
            throw new GatewayException($this->translateStatus($result['result']['responseCode']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = (string) $result['result']['systemTraceAuditNumber'];
        $paymentResponse->referenceCode = (string) $result['result']['retrievalReferenceNumber'];
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    private ?string $retrievalReferenceNumber = null;
    private ?string $systemTraceAuditNumber = null;

    public function processCallback(array $params): PaymentResponse
    {
        $this->retrievalReferenceNumber = $params['retrievalReferenceNumber'] ?? null;
        $this->systemTraceAuditNumber = $params['systemTraceAuditNumber'] ?? null;

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['token'] ?? null;
        $paymentResponse->referenceCode = $params['retrievalReferenceNumber'] ?? null;
        $paymentResponse->trackingCode = $params['systemTraceAuditNumber'] ?? null;
        $paymentResponse->cardNumber = $params['maskedPan'] ?? null;
        $paymentResponse->paymentStatus = (($params['responseCode'] ?? null) == '00')
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function request(string $url, array $data): array
    {
        return Http::timeout(10)
            ->withOptions([
                'curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1'],
            ])
            ->post($url, $data)
            ->throw()
            ->json();
    }

    private function encrypt(string $pubKey, string $terminalId, string $password, int $amount): array
    {
        $data = $terminalId . $password . str_pad((string) $amount, 12, '0', STR_PAD_LEFT) . '00';
        $data = hex2bin($data);
        $aesSecretKey = openssl_random_pseudo_bytes(16);
        $ivlen = openssl_cipher_iv_length($cipher = 'AES-128-CBC');
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($data, $cipher, $aesSecretKey, OPENSSL_RAW_DATA, $iv);
        $hmac = hash('sha256', $ciphertext_raw, true);
        $crypttext = '';

        openssl_public_encrypt($aesSecretKey . $hmac, $crypttext, $pubKey);

        return [
            'data' => bin2hex($crypttext),
            'iv' => bin2hex($iv),
        ];
    }

    private function fee(int $amount): int
    {
        $fee = 1_200;
        if ($amount >= 6_000_000) {
            $fee = (int) min(40_000, round($amount * 0.0002));
        }

        return $fee;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? '<null>'));
    }
}
