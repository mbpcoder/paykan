<?php

namespace MbpCoder\Payment\Tests\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Providers\Zarinpal;
use MbpCoder\Payment\Tests\TestCase;

class ZarinpalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::getRepository()->set('channels.ipg.provider.zarinpal.token', 'test-merchant-id');
        Config::getRepository()->set('channels.ipg.provider.zarinpal.send_url', 'https://api.zarinpal.com/pg/v4/payment/request.json');
        Config::getRepository()->set('channels.ipg.provider.zarinpal.payment_url', 'https://www.zarinpal.com/pg/StartPay/');
        Config::getRepository()->set('channels.ipg.provider.zarinpal.verify_url', 'https://api.zarinpal.com/pg/v4/payment/verify.json');
    }

    public function test_initial_returns_success_when_provider_accepts_the_request(): void
    {
        $this->fakeHttp([
            $this->jsonResponse([
                'data' => ['code' => 100, 'authority' => 'A00000000000000000000000000123456'],
                'errors' => [],
            ]),
        ]);

        $response = (new Zarinpal())->initial(10000, 'order-1');

        $this->assertSame(PaymentStatus::SUCCESS, $response->paymentStatus);
        $this->assertSame('A00000000000000000000000000123456', $response->paymentToken);
    }

    public function test_initial_returns_failed_when_provider_rejects_the_request(): void
    {
        $this->fakeHttp([
            $this->jsonResponse([
                'data' => ['code' => -9],
                'errors' => [],
            ]),
        ]);

        $response = (new Zarinpal())->initial(10000, 'order-1');

        $this->assertSame(PaymentStatus::FAILED, $response->paymentStatus);
        $this->assertNull($response->paymentToken);
    }

    public function test_verify_returns_success_and_reference_code(): void
    {
        $this->fakeHttp([
            $this->jsonResponse([
                'data' => ['code' => 100, 'ref_id' => 987654321, 'card_pan' => '502229******1234'],
                'errors' => [],
            ]),
        ]);

        $response = (new Zarinpal())->verify('A00000000000000000000000000123456', 10000);

        $this->assertSame(PaymentStatus::SUCCESS, $response->paymentStatus);
        $this->assertSame('987654321', $response->referenceCode);
    }
}
