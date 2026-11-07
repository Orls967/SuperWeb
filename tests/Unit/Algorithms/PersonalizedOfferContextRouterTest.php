<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Venue\Domain\Algorithms\Personalization\PersonalizedOfferContextRouter;
use Tests\TestCase;

class PersonalizedOfferContextRouterTest extends TestCase
{
    private PersonalizedOfferContextRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new PersonalizedOfferContextRouter;
    }

    public function test_evaluates_eligible_vip_platinum_discount_with_floor_guardrail(): void
    {
        $now = time();
        $result = $this->router->evaluateOffer(
            userId: 888,
            currentZoneId: 5,
            membershipTierCode: 'VIP_PLATINUM',
            baseItemPriceIdr: 2_000_000,
            itemCogsFloorPriceIdr: 1_600_000, // COGS floor
            lastOfferTimestamp: $now - 4000,
            offersSentTonightCount: 1,
            currentTimestamp: $now,
            saltSecret: 'UNIT_TEST_SALT'
        );

        $this->assertTrue($result->isEligible);
        $this->assertEquals(2500, $result->discountBps);
        $this->assertEquals(1_600_000, $result->discountedPriceIdr); // Capped by floor
        $this->assertTrue($result->floorGuardrailTriggered);
        $this->assertTrue($result->verifySignature('UNIT_TEST_SALT'));
    }

    public function test_rejects_when_cooldown_active(): void
    {
        $now = time();
        $result = $this->router->evaluateOffer(
            userId: 888,
            currentZoneId: 5,
            membershipTierCode: 'VIP_PLATINUM',
            baseItemPriceIdr: 2_000_000,
            itemCogsFloorPriceIdr: 1_000_000,
            lastOfferTimestamp: $now - 600, // Only 10 mins ago
            offersSentTonightCount: 1,
            currentTimestamp: $now
        );

        $this->assertFalse($result->isEligible);
        $this->assertEquals('COOLDOWN_ACTIVE', $result->rejectionReason);
    }
}
