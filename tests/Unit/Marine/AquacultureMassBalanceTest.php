<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use DomainException;
use Modules\Marine\Domain\Algorithms\AquacultureMassBalanceVerifier;
use Tests\TestCase;

class AquacultureMassBalanceTest extends TestCase
{
    protected AquacultureMassBalanceVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new AquacultureMassBalanceVerifier;
    }

    public function test_hlso_mass_balance_within_allowable_yield_passes(): void
    {
        // 1000 kg input panen hidup udang, diproses menjadi HLSO (rendemen bersih 59.8% setelah potong glaze 8%)
        $result = $this->verifier->verifyMassBalance(1000.0, 650.0, 'HLSO', 8.0);

        $this->assertTrue($result['is_compliant']);
        $this->assertEquals(59.8, $result['actual_yield_percentage']);
        $this->assertEquals(68.0, $result['max_allowable_yield_percentage']);
        $this->assertEquals('ASC_MSC_MASS_BALANCE_PASSED', $result['verdict']);
    }

    public function test_hlso_mass_balance_exceeding_yield_fails_with_surplus_detection(): void
    {
        // Mencoba mengklaim 800 kg HLSO dari 1000 kg panen hidup (mustahil tanpa pencampuran udang liar/ilegal)
        $result = $this->verifier->verifyMassBalance(1000.0, 800.0, 'HLSO', 8.0);

        $this->assertFalse($result['is_compliant']);
        $this->assertEquals('SUSPECTED_UNAUTHORIZED_BLENDING', $result['verdict']);
        $this->assertGreaterThan(0.0, $result['unaccounted_surplus_kg']);
    }

    public function test_negative_or_zero_input_biomass_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->verifier->verifyMassBalance(0.0, 100.0, 'HLSO');
    }
}
