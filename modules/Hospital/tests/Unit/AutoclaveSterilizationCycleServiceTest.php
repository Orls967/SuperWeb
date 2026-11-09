<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\AutoclaveSterilizationCycleService;
use PHPUnit\Framework\TestCase;

class AutoclaveSterilizationCycleServiceTest extends TestCase
{
    private AutoclaveSterilizationCycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AutoclaveSterilizationCycleService();
    }

    public function test_standard_prevacuum_cycle_passes_when_parameters_met(): void
    {
        $result = $this->service->evaluateSterilizationCycle(
            'CYCLE_PREVACUUM_134C',
            134.5,
            2.15,
            16,
            true, // Spore killed
            true  // Chemical integrator color changed
        );

        $this->assertTrue($result['is_cycle_successful']);
        $this->assertSame(1.0e-6, $result['sterility_assurance_level']);
        $this->assertSame('NO_SPORE_GROWTH_STERILE', $result['biological_verification']);
        $this->assertSame('END_POINT_REACHED', $result['chemical_verification']);
        $this->assertEmpty($result['failure_reasons']);
    }

    public function test_cycle_fails_when_temperature_drops_below_minimum(): void
    {
        $result = $this->service->evaluateSterilizationCycle(
            'CYCLE_PREVACUUM_134C',
            131.0, // di bawah 134.0
            2.15,
            16,
            true,
            true
        );

        $this->assertFalse($result['is_cycle_successful']);
        $this->assertNotEmpty($result['failure_reasons']);
        $this->assertStringContainsString('Suhu sterilisasi tidak mencapai target', $result['failure_reasons'][0]);
    }

    public function test_cycle_fails_when_biological_indicator_shows_bacterial_growth(): void
    {
        $result = $this->service->evaluateSterilizationCycle(
            'CYCLE_GRAVITY_121C',
            121.5,
            1.20,
            32,
            false, // Biological indicator failed
            true
        );

        $this->assertFalse($result['is_cycle_successful']);
        $this->assertSame('SPORE_POSITIVE_CONTAMINATED', $result['biological_verification']);
        $this->assertContains('Indikator biologis Geobacillus stearothermophilus menunjukkan pertumbuhan aktif (spora gagal diinaktivasi).', $result['failure_reasons']);
    }

    public function test_chemical_integrator_failure_rejects_cycle(): void
    {
        $result = $this->service->evaluateSterilizationCycle(
            'CYCLE_HAZARDOUS_WASTE_DECONTAMINATION',
            135.5,
            2.25,
            50,
            true,
            false // Chemical failed
        );

        $this->assertFalse($result['is_cycle_successful']);
        $this->assertSame('INCOMPLETE_REACTION', $result['chemical_verification']);
    }
}
