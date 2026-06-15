<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Exceptions\GatewayException;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\Redirect;
use SoapClient;
use SoapFault;

/**
 * PEC (Parsian / pec.shaparak.ir) — ported from farayaz/larapay. Uses SOAP (ext-soap).
 */
class PEC extends Base implements IPaymentChannel
{
    private array $statuses = [
        '-126' => 'کد شناسایی پذیرنده معتبر نمی‌باشد',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'PEC';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.pec.' . $key, $default);
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        ini_set('default_socket_timeout', '10');
        $data = [
            'requestData' => [
                'LoginAccount' => $this->cfg('login_account'),
                'Amount' => $amount,
                'OrderId' => $trackingCode,
                'CallBackUrl' => $this->callback,
                'AdditionalData' => $trackingCode,
                'Originator' => $this->cfg('mobile', ''),
            ],
        ];
        try {
            $client = new SoapClient('https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?WSDL');
            $response = $client->SalePaymentRequest($data);
        } catch (SoapFault $e) {
            throw new GatewayException($e->getMessage());
        }
        $result = $response->SalePaymentRequestResult;
        if ($result->Status != 0 || $result->Token <= 0) {
            throw new GatewayException($result->Message);
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->paymentToken = (string) $result->Token;
        $paymentResponse->wage = $this->fee($amount);
        $paymentResponse->paymentUrl = $this->payUrl($result->Token);
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
        return 'https://pec.shaparak.ir/NewIPG/?Token=' . $paymentToken;
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        ini_set('default_socket_timeout', '10');
        $data = [
            'requestData' => [
                'LoginAccount' => $this->cfg('login_account'),
                'Token' => $paymentToken,
            ],
        ];
        try {
            $client = new SoapClient('https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?WSDL');
            $response = $client->ConfirmPayment($data);
        } catch (SoapFault $e) {
            throw new GatewayException($e->getMessage());
        }
        $result = $response->ConfirmPaymentResult;

        if ($result->Status != 0 || $result->RNN <= 0) {
            throw new GatewayException($this->translateStatus($result->Status));
        }

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber ?? ($result->CardNumberMasked ?? null);
        $paymentResponse->trackingCode = (string) $result->RNN;
        $paymentResponse->referenceCode = (string) $result->RNN;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->paymentToken = $params['Token'] ?? null;
        $paymentResponse->referenceCode = $params['RRN'] ?? null;
        $paymentResponse->cardNumber = $params['HashCardNumber'] ?? null;
        $paymentResponse->trackingCode = isset($params['OrderId']) ? (string) $params['OrderId'] : null;
        $paymentResponse->paymentStatus = (($params['Status'] ?? null) == 0 && ($params['Status'] ?? null) !== null)
            ? PaymentStatus::SUCCESS
            : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
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
        return $this->statuses[$code] ?? ((string) ($code ?? 'failed'));
    }
}
