<?php

namespace MbpCoder\Payment\Providers;

use MbpCoder\Payment\Support\Redirect;
use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\IPaymentChannel;
use MbpCoder\Payment\Models\PaymentResponse;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Support\Http\Http;

class PayStar extends Base implements IPaymentChannel
{

    //
    //
    private array $codeMap = [
        '1' => 'موفق',
        '-1' => 'درخواست نامعتبر (خطا در پارامترهای ورودی)',
        '-2' => 'درگاه فعال نیست',
        '-3' => 'توکن تکراری است',
        '-4' => 'مبلغ بیشتر از سقف مجاز درگاه است',
        '-5' => 'شناسه ref_num معتبر نیست',
        '-6' => 'تراکنش قبلا وریفای شده است',
        '-7' => 'پارامترهای ارسال شده نامعتبر است',
        '-8' => 'تراکنش را نمیتوان وریفای کرد',
        '-9' => 'تراکنش وریفای نشد',
        '-98' => 'تراکنش ناموفق',
        '-99' => 'خطای سامانه'
    ];

    private Client $client;
    private string $gateWayId;
    private string $baseUrl;
    private string $currency;

    /**
     * PayPaymentChannel constructor.
     */
    public function __construct(string|null $token = null)
    {
        parent::__construct();

        $this->token = Config::get('channels.ipg.provider.pay_star.token');
        $this->gateWayId = Config::get('channels.ipg.provider.pay_star.gateway_id');
        $this->baseUrl = Config::get('channels.ipg.provider.pay_star.base_url');
        $this->currency = 'IRR';

        $this->name = "PayStar";
    }

    public function initial(int $amount, string|int $trackingCode, string|null $description = null): PaymentResponse
    {
        $response = Http::acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->gateWayId,
            ])->withOptions(["verify" => false])
            ->post($this->baseUrl . '/create', [
                'amount' => $amount,
                'order_id' => $trackingCode,
                'description' => $description,
                'callback' => $this->callback,
                'sign' =>
                    hash_hmac(
                        'SHA512',
                        $amount . '#' . $trackingCode . '#' . $this->callback,
                        $this->token
                    ),
            ]);

        $result = $response->object();

        $paymentResponse = new  PaymentResponse();
        $paymentResponse->originalResponse = $result;

        $paymentResponse->providerCode = $result->status;
        $paymentResponse->providerMessage = $result->message;
        $paymentResponse->paymentToken = $result->data->token ?? null;
        $paymentResponse->referenceCode = $result->data->ref_num ?? null;
        $paymentResponse->amount = $result->data->payment_amount ?? null;
        $paymentResponse->paymentStatus = $result->status !== 1 ? PaymentStatus::FAILED : PaymentStatus::SUCCESS;
        return $paymentResponse;
    }

    public function pay(string|int $paymentToken)
    {
        return Redirect::to($this->baseUrl . '/payment?token=' . $paymentToken);
    }

    public function payUrl(string|int $paymentToken): string
    {
        return $paymentToken;
    }

    public function verify($paymentToken, $amount, $cardNumber = null, $trackingCode = null): PaymentResponse
    {
        $response = Http::acceptJson()
            ->withOptions(["verify" => false])
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->gateWayId,
            ])->post($this->baseUrl . '/verify', [
                'amount' => $amount,
                'ref_num' => $paymentToken,
                'sign' =>
                    hash_hmac(
                        'SHA512',
                        $amount . '#' . $paymentToken . '#' . $cardNumber . '#' . $trackingCode,
                        $this->token
                    )
            ]);

        $result = $response->object();

        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $result;
        $paymentResponse->providerCode = $result->status;
        $paymentResponse->providerMessage = $result->message;

        if ($result->status !== 1) {
            $paymentResponse->paymentStatus = PaymentStatus::FAILED;
        } else {
            $paymentResponse->amount = $result->data->price;
            $paymentResponse->cardNumber = $result->data->card_number;
            $paymentResponse->referenceCode = $result->data->ref_num;
            $paymentResponse->paymentStatus = PaymentStatus::SUCCESS;
        }
        return $paymentResponse;
    }

    public function processCallback(array $params): PaymentResponse
    {
        $paymentResponse = new PaymentResponse();
        $paymentResponse->originalResponse = $params;

        $paymentResponse->trackingCode = $params['order_id'];
        $paymentResponse->bankTrackingCode = $params['tracking_code'] ?? null;
        $paymentResponse->referenceCode = $params['ref_num'];
        $paymentResponse->cardNumber = $params['card_number'] ?? null;
        $paymentResponse->providerCode = $params['status'];
        $paymentResponse->providerMessage = isset($params['status']) ? $this->codeMap[$params['status']] : null;

        $paymentResponse->paymentStatus = ($params['status'] != 1) ? PaymentStatus::FAILED : PaymentStatus::SUCCESS;

        return $paymentResponse;
    }

    public function personalPaymentPage($url, $amount, $name, $phone, $description)
    {
        return $url;
    }

    private function translateStatus($status)
    {
        $status = (string)$status;

        $translations = [
            '1' => 'موفق',
            '-1' => 'درخواست نامعتبر (خطا در پارامترهای ورودی)',
            '-2' => 'درگاه فعال نیست',
            '-3' => 'توکن تکراری است',
            '-4' => 'مبلغ بیشتر از سقف مجاز درگاه است',
            '-5' => 'شناسه ref_num معتبر نیست',
            '-6' => 'تراکنش قبلا وریفای شده است',
            '-7' => 'پارامترهای ارسال شده نامعتبر است',
            '-8' => 'تراکنش را نمیتوان وریفای کرد',
            '-9' => 'تراکنش وریفای نشد',
            '-98' => 'تراکنش ناموفق',
            '-99' => 'خطای سامانه'
        ];

        $unknownError = 'خطای ناشناخته رخ داده است.';

        return array_key_exists($status, $translations) ? $translations[$status] : $unknownError;
    }
}
