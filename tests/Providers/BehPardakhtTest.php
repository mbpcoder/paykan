<?php

namespace MbpCoder\Payment\Tests\Providers;

use MbpCoder\Payment\Config\Config;
use MbpCoder\Payment\Models\PaymentStatus;
use MbpCoder\Payment\Providers\BehPardakht;
use MbpCoder\Payment\Tests\TestCase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('soap')]
class BehPardakhtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::getRepository()->set('channels.ipg.provider.beh_pardakht.terminal_id', '12345678');
        Config::getRepository()->set('channels.ipg.provider.beh_pardakht.username', 'test-user');
        Config::getRepository()->set('channels.ipg.provider.beh_pardakht.password', 'test-pass');
    }

    public function test_initial_returns_success_when_provider_accepts_the_request(): void
    {
        $this->fakeSoap([
            (object) ['return' => '0,ref-token-123'],
        ]);

        $response = (new BehPardakht())->initial(10000, 'order-1');

        $this->assertSame(PaymentStatus::SUCCESS, $response->paymentStatus);
        $this->assertSame('ref-token-123', $response->paymentToken);
    }

    public function test_initial_returns_failed_when_provider_rejects_the_request(): void
    {
        $this->fakeSoap([
            (object) ['return' => '21'],
        ]);

        $response = (new BehPardakht())->initial(10000, 'order-1');

        $this->assertSame(PaymentStatus::FAILED, $response->paymentStatus);
        $this->assertNull($response->paymentToken);
    }

    public function test_verify_settles_a_successful_transaction(): void
    {
        $this->fakeSoap([
            (object) ['return' => '0'], // bpVerifyRequest
            (object) ['return' => '0'], // bpSettleRequest
        ]);

        $response = (new BehPardakht())->verify('ref-token-123', 10000, null, 'order-1');

        $this->assertSame(PaymentStatus::SUCCESS, $response->paymentStatus);
    }
}
