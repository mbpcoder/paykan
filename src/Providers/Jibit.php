<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Support\Redirect;
use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\Http\Http;

class Jibit extends Base implements IPaymentChannel
{
    private array $codeMap = [
        'client.not_active' => 'سرویس گیرنده فعال نیست',
        'amount.is_required' => 'مبلغ درخواستی اجباری است',
        'amount.not_enough' => 'مبلغ درخواستی خیلی کم است.',
        'wage.is_invalid' => 'پورسانت غیرمعتبر است',
        'fee_as_wage.wage_must_be_zero' => 'پورسانت باستی صفر باشد',
        'amount_plus_wage.max_value_exceeded' => 'جمع مقدار مبلغ و پورسانت زیاد است',
        'amount_plus_wage.permitted_value_exceeded' => 'جمع مقدار مبلغ و پورسانت نا معتبر است',
        'currency.is_required' => 'واحد پول اجباری است',
        'callbackUrl.is_required' => 'آدرس برگشت اجباری است',
        'callbackUrl.is_invalid' => 'آدرس برگشت معتبر نیست',
        'callbackUrl.max_length' => 'آدرس برگشت طولش خیلی زیاد است',
        'clientReferenceNumber.is_required' => 'کد مرجع مربوط به مشتری اجباری است',
        'clientReferenceNumber.duplicated' => 'کد مرجع مربوط به مشتری تکراری است',
        'payerCardNumber.is_invalid' => 'شماره کارت واریز کننده نا متعبر است',
        'payerNationalCode.is_invalid' => 'شماره ملی واریز کننده نا متعبر است',
        'userIdentifier.max_length' => 'مشخصه کاربر طولش زیاد است',
        'payerMobileNumber.is_invalid' => 'شماره موبایل واریز کننده نامعتبر است',
        'payerMobileNumber.in_blacklist' => 'شماره موبایل پرداخت کننده در لیست سیاه است',
        'payerCardNumber_and_payerCardNumbers.just_one_of_them_is_permitted' => 'یکی از دو گزینه شماره کارت یا شماره کارت ها اجازی ارسال دارد',
        'description.max_length' => 'طول توضیحات زیاد است',
        'ip.not_trusted' => 'آی پی معتبر نشده است',
        'security.auth_required' => 'اعتبار سنجی اجباری است',
        'token.verification_failed' => 'تایید توکن موفقیت آمیز نبود',
        'web.invalid_or_missing_body' => 'بدنه درخواست یا معتبر نیست یا وجود ندارد',
        'server.error' => 'یه خطایی پیش آمده است',
    ];

    private Http $httpClient;
    private string $secretKey;
    private string $baseUrl;
    private string $currency;
    private string|null $proxy = null;

    /**
     * PayPaymentChannel constructor.
     */
    public function __construct(string|null $token = null)
    {
        parent::__construct();

        $this->token = Config::get('channels.ipg.provider.jibit.api_key');
        $this->secretKey = Config::get('channels.ipg.provider.jibit.secret_key');
        $this->baseUrl = Config::get('channels.ipg.provider.jibit.base_url');
        $this->proxy = Config::get('channels.ipg.provider.jibit.proxy');
        $this->currency = 'IRR';

        $this->name = "Jibit";

        $option = [
            'verify' => false,
        ];

        if ($this->proxy !== null) {
            $option['proxy'] = $this->proxy;
        }

        $this->httpClient = Http::withOptions($option)
            ->acceptJson()
            ->withUserAgent('Jibit.class Rest Api')
            ->withHeader('Content-Type', 'application/json');
    }

    private function getAccessToken()
    {
        $accessToken = null;
        $response = $this->httpClient->post($this->baseUrl . '/tokens', [
            'apiKey' => $this->token,
            'secretKey' => $this->secretKey,
        ]);
        if ($response->ok()) {
            $data = $response->json();
            $accessToken = 'Bearer ' . $data['accessToken'];
            $refreshToken = $data['refreshToken'];
        }
        return $accessToken;
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $accessToken = $this->getAccessToken();

        $response = $this->httpClient
            ->withHeader('Authorization', $accessToken)
            ->post($this->baseUrl . '/purchases', [
                'additionalData' => null,
                'amount' => $amount,
                'callbackUrl' => $this->callback,
                'clientReferenceNumber' => $trackingCode,
                'currency' => $this->currency,
                'userIdentifier' => '09157009100',
                'description' => $description,
            ]);

        $data = $response->json();

        $paymentResponse = new  PaymentResponse();
        $paymentResponse->originalResponse = $data;
        $paymentResponse->providerCode = null;
        $paymentResponse->providerMessage = isset($data['errors'][0]['code']) ? $this->codeMap[$data['errors'][0]['code']] : null;
        $paymentResponse->paymentToken = $data['purchaseIdStr'] ?? null;
        $paymentResponse->paymentStatus = isset($data['errors']) ? PaymentStatus::FAILED : PaymentStatus::SUCCESS;
        $paymentResponse->paymentUrl = $data['pspSwitchingUrl'] ?? null;
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
        $accessToken = $this->getAccessToken();

        $response = $this->httpClient
            ->withHeader('Authorization', $accessToken)
            ->get($this->baseUrl . '/purchases/' . $paymentToken . '/verify');

        $data = $response->json();

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $data;
        $paymentResponse->paymentToken = $paymentToken;

        if ($data['status'] === 'SUCCESSFUL') {
            $paymentResponse->providerCode = $data['status'];
            $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        } else {
            $paymentResponse->providerCode = $data['status'];
            $paymentResponse->providerMessage = isset($data['status']) ? $this->codeMap[$data['status']] : null;
            $paymentResponse->paymentStatus = PaymentStatus::FAILED;
        }
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['purchaseId'];
        $paymentResponse->trackingCode = $params['clientReferenceNumber'];
        $paymentResponse->cardNumber = $params['payerMaskedCardNumber'];
        $paymentResponse->amount = $params['amount'];
        $paymentResponse->wage = $params['wage'];
        $paymentResponse->payerIp = $params['payerIp'];
        if ($params['status'] === 'FAILED') {
            $paymentResponse->providerMessage = $params['failReason'];
            $paymentResponse->providerCode = null;
            $paymentResponse->paymentStatus = PaymentStatus::FAILED;
        } else {
            $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        }

        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }
}
