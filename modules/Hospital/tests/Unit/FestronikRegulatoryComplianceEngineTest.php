<?php

declare(strict_types=1);

namespace Modules\Hospital\tests\Unit;

use Modules\Hospital\Domain\Services\FestronikRegulatoryComplianceEngine;
use Tests\TestCase;

class FestronikRegulatoryComplianceEngineTest extends TestCase
{
    private FestronikRegulatoryComplianceEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FestronikRegulatoryComplianceEngine();
    }

    public function test_tps_storage_duration_compliance_under_48_hours(): void
    {
        // Limbah infeksius pada suhu ambient selama 24 jam -> Patuh
        $eval = $this->engine->verifyTpsStorageCompliance('INFECTIOUS', 26.5, 24);

        $this->assertTrue($eval['is_compliant']);
        $this->assertSame('COMPLIANT', $eval['compliance_status']);
        $this->assertSame(0, $eval['exceeded_hours']);
    }

    public function test_tps_storage_duration_violation_over_48_hours(): void
    {
        // Limbah benda tajam disimpan 60 jam pada suhu ambient -> Melanggar batas 48 jam
        $eval = $this->engine->verifyTpsStorageCompliance('SHARPS', 28.0, 60);

        $this->assertFalse($eval['is_compliant']);
        $this->assertSame('OVERDUE_VIOLATION', $eval['compliance_status']);
        $this->assertSame(12, $eval['exceeded_hours']);
        $this->assertNotNull($eval['warning_message']);
    }

    public function test_thermal_incineration_standards_certification(): void
    {
        // Uji kondisi ideal: Kamar 1 = 850°C, Kamar 2 = 1100°C, Waktu tinggal = 2.5s, Emisi = 25 mg/Nm3
        $certOk = $this->engine->certifyThermalIncineration(850.0, 1100.0, 2.5, 25.0);
        $this->assertTrue($certOk['is_destruction_certified']);
        $this->assertSame(99.99, $certOk['destruction_efficiency_pct']);

        // Uji kondisi gagal: Kamar 1 = 650°C (kurang dari 800°C)
        $certFail = $this->engine->certifyThermalIncineration(650.0, 950.0, 1.5, 60.0);
        $this->assertFalse($certFail['is_destruction_certified']);
        $this->assertNotEmpty($certFail['disapproval_reasons']);
    }
}
