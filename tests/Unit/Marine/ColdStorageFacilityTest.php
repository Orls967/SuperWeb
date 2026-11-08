<?php

declare(strict_types=1);

namespace Tests\Unit\Marine;

use Database\Seeders\MarineAquacultureT1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marine\Application\Services\AquacultureColdStorageFacilityService;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Tests\TestCase;

class ColdStorageFacilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarineAquacultureT1Seeder::class);
    }

    public function test_haccp_ccp2_freezing_verification(): void
    {
        $service = new AquacultureColdStorageFacilityService;
        $lot = AquacultureExportLot::first();

        // 1. Suhu inti -23.5°C <= -18.0°C (Lolos)
        $compliantAudit = [
            'facility_code' => 'ID-PLANT-042',
            'freezing_method' => 'IQF_LIQUID_NITROGEN',
            'measured_core_temperature_celsius' => -23.5,
            'freezing_duration_minutes' => 45,
            'glazing_percentage' => 8.0,
            'haccp_officer_name' => 'QA Officer Supriyatno',
        ];

        $res = $service->verifyFreezingHaccpCcp2($lot->id, $compliantAudit);

        $this->assertTrue($res['is_ccp2_compliant']);
        $this->assertEquals(-23.5, $res['core_temperature_celsius']);
        $this->assertEquals('HACCP_CCP2_VERIFIED_FROZEN_SOLID', $res['verdict']);

        // 2. Suhu inti -14.0°C > -18.0°C (Gagal)
        $breachAudit = [
            'facility_code' => 'ID-PLANT-042',
            'freezing_method' => 'IQF_BLAST',
            'measured_core_temperature_celsius' => -14.0,
            'freezing_duration_minutes' => 35,
            'glazing_percentage' => 8.0,
            'haccp_officer_name' => 'QA Officer Supriyatno',
        ];

        $resBreach = $service->verifyFreezingHaccpCcp2($lot->id, $breachAudit);
        $this->assertFalse($resBreach['is_ccp2_compliant']);
        $this->assertEquals('CRITICAL_TEMPERATURE_BREACH_REFREEZE_MANDATORY', $resBreach['verdict']);
    }
}
