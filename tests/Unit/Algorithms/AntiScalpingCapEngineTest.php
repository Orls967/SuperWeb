<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingCapEngine;
use Modules\Venue\Domain\Algorithms\AntiScalping\AntiScalpingInput;
use Tests\TestCase;

final class AntiScalpingCapEngineTest extends TestCase
{
    private AntiScalpingCapEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new AntiScalpingCapEngine;
    }

    public function test_allows_valid_asking_price_within_one_hundred_twenty_percent(): void
    {
        $input = new AntiScalpingInput(
            originalFaceValueIdr: 1000000,
            askingPriceIdr: 1200000,
            transferCount: 0,
            userActiveTicketCount: 1,
            velocitySeconds: 2.5
        );

        $result = $this->engine->evaluate($input);

        $this->assertTrue($result->isAllowed);
        $this->assertSame(1200000, $result->effectivePriceIdr);
        $this->assertNull($result->rejectionReason);
        $this->assertNotNull($result->certificate);
        $this->assertTrue($result->certificate->isValid());
    }

    public function test_rejects_asking_price_exceeding_one_hundred_twenty_percent(): void
    {
        $input = new AntiScalpingInput(
            originalFaceValueIdr: 1000000,
            askingPriceIdr: 1250000,
            transferCount: 0,
            userActiveTicketCount: 1,
            velocitySeconds: 3.0
        );

        $result = $this->engine->evaluate($input);

        $this->assertFalse($result->isAllowed);
        $this->assertSame(0, $result->effectivePriceIdr);
        $this->assertStringContainsString('melebihi batas anti-scalping', (string) $result->rejectionReason);
    }

    public function test_rejects_single_transfer_rule_violation(): void
    {
        $input = new AntiScalpingInput(
            originalFaceValueIdr: 1000000,
            askingPriceIdr: 1100000,
            transferCount: 1,
            userActiveTicketCount: 1,
            velocitySeconds: 2.0
        );

        $result = $this->engine->evaluate($input);

        $this->assertFalse($result->isAllowed);
        $this->assertStringContainsString('maksimum 1 kali', (string) $result->rejectionReason);
    }

    public function test_rejects_excessive_active_ticket_holdings(): void
    {
        $input = new AntiScalpingInput(
            originalFaceValueIdr: 1000000,
            askingPriceIdr: 1000000,
            transferCount: 0,
            userActiveTicketCount: 4,
            velocitySeconds: 2.0
        );

        $result = $this->engine->evaluate($input);

        $this->assertFalse($result->isAllowed);
        $this->assertStringContainsString('maksimum 4 tiket', (string) $result->rejectionReason);
    }

    public function test_rejects_bot_velocity_under_one_second(): void
    {
        $input = new AntiScalpingInput(
            originalFaceValueIdr: 1000000,
            askingPriceIdr: 1000000,
            transferCount: 0,
            userActiveTicketCount: 1,
            velocitySeconds: 0.4
        );

        $result = $this->engine->evaluate($input);

        $this->assertFalse($result->isAllowed);
        $this->assertStringContainsString('bot velocity', (string) $result->rejectionReason);
    }
}
