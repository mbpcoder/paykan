<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Exceptions\GatewayException;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\IRefundable;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\FormRedirect;
use MbpCoder\Payment\Support\Soap\SoapClientFactory;
use SoapFault;

/**
 * BehPardakht (Mellat) — ported from farayaz/larapay. Uses SOAP (ext-soap).
 *
 * Verify flow adaptation: $paymentToken carries the SaleReferenceId returned
 * on the callback, $trackingCode carries the original order id, and
 * $cardNumber carries CardHolderPan.
 */
class BehPardakht extends Base implements IPaymentChannel, IRefundable
{
    private array $statuses = [
        11 => 'شماره کارت نامعتبر است',
        12 => 'موجودی کافی نیست',
        13 => 'رمز نادرست است',
        14 => 'تعداد دفعات وارد کردن رمز بیش از حد مجاز است',
        15 => 'کارت نامعتبر است',
        16 => 'دفعات برداشت وجه بیش از حد مجاز است',
        17 => 'کاربر از انجام تراکنش منصرف شده است',
        18 => 'تاریخ انقضای کارت گذشته است',
        19 => 'مبلغ برداشت وجه بیش از حد مجاز است',
        111 => 'صادر کننده کارت نامعتبر است',
        112 => 'خطای سوییچ صادر کننده کارت',
        113 => 'پاسخی از صادر کننده کارت دریافت نشد',
        114 => 'دارنده این کارت مجاز به انجام این تراکنش نیست',
        21 => 'پذیرنده نامعتبر است',
        23 => 'خطای امنیتی رخ داده است',
        24 => 'اطلاعات کاربری پذیرنده نامعتبر است',
        25 => 'مبلغ نامعتبر است',
        31 => 'پاسخ نامعتبر است',
        32 => 'فرمت اطلاعات وارد شده صحیح نمی‌باشد',
        33 => 'حساب نامعتبر است',
        34 => 'خطای سیستمی',
        35 => 'تاریخ نامعتبر است',
        41 => 'شماره درخواست تکراری است',
        42 => 'تراکنش Sale یافت نشد',
        43 => 'قبلا درخواست Verfiy داده شده است',
        44 => 'درخواست Verfiy یافت نشد',
        45 => 'تراکنش Settle شده است',
        46 => 'تراکنش Settle نشده است',
        47 => 'تراکنش Settle یافت نشد',
        48 => 'تراکنش Reverse شده است',
        49 => 'تراکنش Refund یافت نشد.',
        412 => 'شناسه قبض نادرست است',
        413 => 'شناسه پرداخت نادرست است',
        414 => 'سازمان صادر کننده قبض نامعتبر است',
        415 => 'زمان جلسه کاری به پایان رسیده است',
        416 => 'خطا در ثبت اطلاعات',
        417 => 'شناسه پرداخت کننده نامعتبر است',
        418 => 'اشکال در تعریف اطلاعات مشتری',
        419 => 'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است',
        421 => 'IP نامعتبر است',
        51 => 'تراکنش تکراری است',
        54 => 'تراکنش مرجع موجود نیست',
        55 => 'تراکنش نامعتبر است',
        61 => 'خطا در واریز',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'BehPardakht';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.beh_pardakht.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $method = $this->cfg('is_credit') ? 'bpVirtualPayRequest' : 'bpPayRequest';
        $params = [
            'terminalId' => $this->cfg('terminal_id'),
            'userName' => $this->cfg('username'),
            'userPassword' => $this->cfg('password'),
            'orderId' => $trackingCode,
            'amount' => $amount,
            'localDate' => date('Ymd'),
            'localTime' => date('His'),
            'additionalData' => '',
            'callBackUrl' => $this->callback,
            'payerId' => 0,
        ];

        ini_set('default_socket_timeout', '10');
        try {
            $client = SoapClientFactory::make($this->url('services/pgw?wsdl'));
            $response = $client->{$method}($params);
        } catch (SoapFault $e) {
            throw new GatewayException($e->getMessage());
        }

        $result = explode(',', $response->return);

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $response->return;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->providerCode = $result[0];
        $paymentResponse->providerMessage = $result[0] != '0' ? $this->translateStatus($result[0]) : null;
        $paymentResponse->paymentToken = $result[0] == '0' ? ($result[1] ?? null) : null;
        $paymentResponse->paymentStatus = $result[0] == '0' ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), ['RefId' => $paymentToken]);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $this->url('startpay.mellat');
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $data = [
            'terminalId' => $this->cfg('terminal_id'),
            'userName' => $this->cfg('username'),
            'userPassword' => $this->cfg('password'),
            'orderId' => $trackingCode,
            'saleOrderId' => $trackingCode,
            'saleReferenceId' => $paymentToken,
        ];

        ini_set('default_socket_timeout', '10');
        try {
            $client = SoapClientFactory::make($this->url('services/pgw?wsdl'));
            $client->bpVerifyRequest($data);
            $response = $client->bpSettleRequest($data);
        } catch (SoapFault $e) {
            throw new GatewayException($e->getMessage());
        }

        $success = $response->return == '0' || $response->return == '45';

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $response->return;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->referenceCode = (string) $paymentToken;
        $paymentResponse->providerCode = (string) $response->return;
        $paymentResponse->providerMessage = $success ? null : $this->translateStatus($response->return);
        $paymentResponse->paymentStatus = $success ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function refund(string|int $paymentToken, int $amount, string|int|null $trackingCode = null): bool
    {
        $data = [
            'terminalId' => $this->cfg('terminal_id'),
            'userName' => $this->cfg('username'),
            'userPassword' => $this->cfg('password'),
            'orderId' => $trackingCode,
            'saleOrderId' => $trackingCode,
            'saleReferenceId' => $paymentToken,
        ];

        ini_set('default_socket_timeout', '10');
        try {
            $client = SoapClientFactory::make($this->url('services/pgw?wsdl'));
            $response = $client->bpReversalRequest($data);
        } catch (SoapFault $e) {
            throw new GatewayException($e->getMessage());
        }

        return $response->return == '0';
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->trackingCode = $params['SaleOrderId'] ?? null;
        $paymentResponse->paymentToken = $params['SaleReferenceId'] ?? ($params['RefId'] ?? null);
        $paymentResponse->referenceCode = $params['SaleReferenceId'] ?? null;
        $paymentResponse->traceNumber = $params['SaleReferenceId'] ?? null;
        $paymentResponse->cardNumber = $params['CardHolderPan'] ?? null;
        $paymentResponse->providerCode = $params['ResCode'] ?? null;
        $paymentResponse->paymentStatus = (($params['ResCode'] ?? null) === '0')
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function url(string $path): string
    {
        $base = $this->cfg('base_url');
        if ($base !== null) {
            return rtrim($base, '/') . '/' . $path;
        }

        $channel = $this->cfg('is_credit') ? 'pgwCreditchannel' : 'pgwchannel';

        return 'https://bpm.shaparak.ir/' . $channel . '/' . $path;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
