<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use InvalidArgumentException;
use Modules\Venue\Domain\Algorithms\MembershipTierCertificate;
use Modules\Venue\Domain\Algorithms\MembershipTierEngine;
use Modules\Venue\Domain\Algorithms\MembershipTierInput;
use Modules\Venue\Domain\Algorithms\MembershipTierVerifier;
use Tests\TestCase;

final class MembershipTierEngineTest extends TestCase
{
    private MembershipTierEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new MembershipTierEngine;
    }

    public function test_natural_qualification_infinity_and_moon(): void
    {
        // 1. Infinity (Spend >= 75jt DAN Visits >= 24)
        $inputInfinity = new MembershipTierInput(
            currentTier: 'sun',
            spend365DaysIdr: 75_000_000,
            visits365Days: 24,
            isAnniversaryEvaluation: false
        );
        $resInfinity = $this->engine->evaluate($inputInfinity);
        $this->assertSame('infinity', $resInfinity->resolvedTier);
        $this->assertTrue($resInfinity->eligibleForBottleLocker);
        $this->assertTrue($resInfinity->certificate->isValid());

        // 2. Moon (Spend >= 20jt ATAU Visits >= 12)
        $inputMoonA = new MembershipTierInput(
            currentTier: 'sun',
            spend365DaysIdr: 25_000_000,
            visits365Days: 5,
            isAnniversaryEvaluation: false
        );
        $resMoonA = $this->engine->evaluate($inputMoonA);
        $this->assertSame('moon', $resMoonA->resolvedTier);

        $inputMoonB = new MembershipTierInput(
            currentTier: 'sun',
            spend365DaysIdr: 10_000_000,
            visits365Days: 14,
            isAnniversaryEvaluation: false
        );
        $resMoonB = $this->engine->evaluate($inputMoonB);
        $this->assertSame('moon', $resMoonB->resolvedTier);

        // 3. Sun
        $inputSun = new MembershipTierInput(
            currentTier: 'sun',
            spend365DaysIdr: 19_999_999,
            visits365Days: 11,
            isAnniversaryEvaluation: false
        );
        $resSun = $this->engine->evaluate($inputSun);
        $this->assertSame('sun', $resSun->resolvedTier);
    }

    public function test_anniversary_grace_period_and_step_downgrade(): void
    {
        // Infinity member dengan spend 62jt (>= 80% dari 75jt = 60jt) saat anniversary -> Grace Period
        $inputGrace = new MembershipTierInput(
            currentTier: 'infinity',
            spend365DaysIdr: 62_000_000,
            visits365Days: 10,
            isAnniversaryEvaluation: true
        );
        $resGrace = $this->engine->evaluate($inputGrace);
        $this->assertSame('infinity', $resGrace->resolvedTier);
        $this->assertTrue($resGrace->inGracePeriod);

        // Infinity member dengan spend 40jt (< 60jt) -> Turun 1 tingkat ke Moon
        $inputDowngrade = new MembershipTierInput(
            currentTier: 'infinity',
            spend365DaysIdr: 40_000_000,
            visits365Days: 8,
            isAnniversaryEvaluation: true
        );
        $resDowngrade = $this->engine->evaluate($inputDowngrade);
        $this->assertSame('moon', $resDowngrade->resolvedTier);
        $this->assertFalse($resDowngrade->inGracePeriod);
    }

    public function test_points_calculation_multipliers(): void
    {
        // Belanja Rp 1.000.000
        // Sun: 100 pts
        // Moon (1.5x): 150 pts
        // Infinity (2.0x): 200 pts
        $this->assertSame(100, $this->engine->calculatePoints(1_000_000, 'sun'));
        $this->assertSame(150, $this->engine->calculatePoints(1_000_000, 'moon'));
        $this->assertSame(200, $this->engine->calculatePoints(1_000_000, 'infinity'));
    }

    public function test_tampered_membership_certificate_is_rejected(): void
    {
        $input = new MembershipTierInput('sun', 80_000_000, 30, false);
        $res = $this->engine->evaluate($input);

        $tampered = new MembershipTierCertificate(
            currentTier: 'sun',
            spend365DaysIdr: 80_000_000,
            visits365Days: 30,
            isAnniversaryEvaluation: false,
            resolvedTier: 'sun', // dimanipulasi
            inGracePeriod: false,
            eligibleForBottleLocker: true,
            hmacSignature: $res->certificate->hmacSignature
        );

        $this->expectException(InvalidArgumentException::class);
        MembershipTierVerifier::verify($tampered);
    }
}
