<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\IRefundable;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\FormRedirect;
use MbpCoder\Payment\Support\Http\Http;

/**
 * Sep (Saman Bank) — REST MobilePG API.
 *
 * Verify flow adaptation: $paymentToken carries the RefNum returned on the
 * callback, $trackingCode carries the original ResNum (order id).
 */
class Sep extends Base implements IPaymentChannel, IRefundable
{
    private string $url = 'https://sep.shaparak.ir';

    private array $statuses = [
        '-1' => 'api ارسالی نباید خالی باشد.',
        '-2' => 'merchant غیرفعال است.',
        '-3' => 'مقدار amount نباید خالی باشد.',
        '-4' => 'مقدار amount باید به صورت عدد باشد.',
        '-5' => 'مقدار amount نباید کمتر از 1000 ریال باشد.',
        '-6' => 'redirect url ارسالی نباید خالی باشد.',
        '-7' => 'callback url نامعتبر است.',
        '-8' => 'تراکنش تکراری است.',
        '-9' => 'خطای داخلی سامانه.',
    ];

    public function __construct(string|null $token = null)
    {
        parent::__construct();
        $this->name = 'Sep';
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return Config::get('channels.ipg.provider.sep.' . $key, $default);
    }

    private function baseUrl(): string
    {
        $base = $this->cfg('base_url');

        return $base !== null ? rtrim($base, '/') : $this->url;
    }

    #[\Override]
    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $params = [
            'Action' => 'Token',
            'Amount' => $amount,
            'Wage' => '0',
            'TerminalId' => $this->cfg('terminal_id'),
            'ResNum' => (string) $trackingCode,
            'RedirectURL' => $this->callback,
        ];

        $result = $this->request($this->baseUrl() . '/MobilePG/MobilePayment', $params);
        $status = (int) ($result['Status'] ?? -1);
        $success = $status === 1;

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->trackingCode = (string) $trackingCode;
        $paymentResponse->providerCode = (string) $status;
        $paymentResponse->providerMessage = $success ? null : $this->translateStatus($status);
        $paymentResponse->paymentToken = $success ? ($result['Token'] ?? null) : null;
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = $success ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function pay(string|int $paymentToken)
    {
        return FormRedirect::render($this->payUrl($paymentToken), [
            'Token' => $paymentToken,
        ]);
    }

    #[\Override]
    public function payUrl(string|int $paymentToken): string
    {
        return $this->cfg('pay_base_url') ?? ($this->baseUrl() . '/Payment/PaymentController/SendToIPGW');
    }

    #[\Override]
    public function verify($paymentToken, $amount, string|null $cardNumber = null, string|int|null $trackingCode = null): PaymentResponse
    {
        $params = [
            'Action' => 'Verify',
            'RefNum' => $paymentToken,
            'TerminalId' => $this->cfg('terminal_id'),
        ];

        $result = $this->request($this->baseUrl() . '/MobilePG/MobilePayment', $params);
        $status = (int) ($result['Status'] ?? -1);
        $success = $status > 0;

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->paymentToken = (string) $paymentToken;
        $paymentResponse->cardNumber = $cardNumber;
        $paymentResponse->trackingCode = $trackingCode !== null ? (string) $trackingCode : null;
        $paymentResponse->referenceCode = (string) $paymentToken;
        $paymentResponse->providerCode = (string) $status;
        $paymentResponse->providerMessage = $success ? null : $this->translateStatus($status);
        $paymentResponse->wage = 0;
        $paymentResponse->paymentStatus = $success ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function refund(string|int $paymentToken, int $amount, string|int|null $trackingCode = null): bool
    {
        $params = [
            'Action' => 'Reverse',
            'RefNum' => $paymentToken,
            'TerminalId' => $this->cfg('terminal_id'),
        ];

        $result = $this->request($this->baseUrl() . '/MobilePG/MobilePayment', $params);

        return (int) ($result['Status'] ?? -1) > 0;
    }

    #[\Override]
    public function processCallback(array $params): PaymentResponse
    {
        $state = $params['State'] ?? null;
        $success = $state === 'OK';

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;
        $paymentResponse->trackingCode = $params['ResNum'] ?? null;
        $paymentResponse->paymentToken = $params['RefNum'] ?? null;
        $paymentResponse->referenceCode = $params['RefNum'] ?? null;
        $paymentResponse->traceNumber = $params['TraceNo'] ?? null;
        $paymentResponse->cardNumber = $params['SecurePan'] ?? null;
        $paymentResponse->providerCode = $state;
        $paymentResponse->paymentStatus = $success ? PaymentStatus::SUCCESS : PaymentStatus::FAILED;
        return $paymentResponse;
    }

    #[\Override]
    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function translateStatus(int|string|null $code): string
    {
        return $this->statuses[(string) $code] ?? ((string) ($code ?? 'failed'));
    }

    private function request(string $url, array $data): array
    {
        return Http::timeout(10)
            ->post($url, $data)
            ->throw()
            ->json();
    }
}
