<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Exceptions\GatewayException;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\Http\Http;
use MbpCoder\Payment\Support\Redirect;

/**
 * IsipaymentSamin (https://ipg.isipayment.ir/) — ported from farayaz/larapay.
 */
class IsipaymentSamin extends Base implements IPaymentChannel
{
    private string $url = 'https://ipg.isipayment.ir/';

    private array $statuses = [
        'failed' => 'ناموفق',
        'id-mismatch' => 'عدم تطبیق شناسه بازگشتی',
        'amount-mismatch' => 'عدم تطبیق مبلغ بازگشتی',
        'token-mismatch' => 'عدم تطبیق توکن بازگشتی',
        'connection-exception' => 'خطا ارتباطی با سرویس دهنده',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'IsipaymentSamin';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.isipayment_samin.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $url = $this->url . 'api/IPGRequestPurchase';
        $params = [
            'Amount' => $amount,
            'PurchaseDate' => date('c'),
            'MerchantReferenceNumber' => $trackingCode,
            'ReturnURL' => $this->callback,
            'Type' => $this->cfg('type'),
            'NumberOfInstallment' => $this->cfg('number_of_installment'),
        ];

        $result = $this->request($url, $params);
        if ($result['ResponseCode'] != 0) {
            throw new GatewayException($result['ResponseInformation']);
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = $result['Token'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentUrl = $this->payUrl($result['Token']);
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->payUrl($paymentToken));
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $this->url . 'IPG?Token=' . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $url = $this->url . 'api/ConfirmTransaction';
        $data = [
            'Token' => $paymentToken,
            'RefNO' => $this->refNo,
            'CONFIRM_TRANSACTION_STATUS' => 1,
        ];
        $result = $this->request($url, $data);
        if ($result['ResponseCode'] != 0) {
            throw new GatewayException($this->translateStatus($result['ResponseInformation']));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = (string) $result['RefNO'];
        $paymentResponse->referenceCode = (string) $result['RefNO'];
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    private ?string $refNo = null;

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $this->refNo = $params['RefNO'] ?? null;

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['Token'] ?? null;
        $paymentResponse->referenceCode = $params['RefNO'] ?? null;
        $paymentResponse->trackingCode = $params['MerchantReferenceNumber'] ?? null;
        $paymentResponse->paymentStatus = (($params['ResponseCode'] ?? null) == 0)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function request(string $url, array $data): array
    {
        $data['MerchantCode'] = $this->cfg('merchant_code');
        $data['MerchantPassword'] = $this->cfg('merchant_password');
        $data['TerminalCode'] = $this->cfg('terminal_code');

        return Http::timeout(10)
            ->post($url, $data)
            ->throw()
            ->json();
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[$code] ?? ((string) ($code ?? '<null>'));
    }
}
