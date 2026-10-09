<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Application\Services\GlobalCommandService;
use Tests\TestCase;

/**
 * Fase 151 — Global Command Tests
 *
 * Covers:
 *  (a) translasi regional Σ = konsolidasi
 *  (b) tax equalization konsisten aturan
 *  (c) entry playbook gate tak bisa dilewati bila prasyarat belum lengkap
 *  (d) FX exposure = Σ posisi regional
 *  (e) group:audit = 0 selisih
 */
class GlobalCommandTest extends TestCase
{
    use RefreshDatabase;

    protected GlobalCommandService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GlobalCommandService::class);
        $this->service->seedGlobalRegions();
    }

    /**
     * (a) & (d) Translasi regional dan perhitungan FX exposure akurat.
     */
    public function test_regional_fx_exposure_and_translation(): void
    {
        $expApac = $this->service->recordFxExposure('APAC', 'SGD', 100000.0, 12000.0, 50000.0);
        $expEmea = $this->service->recordFxExposure('EMEA', 'EUR', 200000.0, 17500.0, 100000.0);

        $this->assertEquals(1200000000.00, (float) $expApac->consolidated_idr);
        $this->assertEquals(3500000000.00, (float) $expEmea->consolidated_idr);

        $totalConsolidated = \DB::table('grp_fx_exposures')->sum('consolidated_idr');
        $this->assertEquals(4700000000.00, (float) $totalConsolidated);
    }

    /**
     * (b) Tax equalization konsisten aturan.
     */
    public function test_tax_equalization_calculation(): void
    {
        // Salary: Rp 100,000,000 / month
        // Home rate: 20% (IDR 20,000,000)
        // Host rate (Europe): 35% (IDR 35,000,000)
        // Excess borne by company: 35m - 20m = 15m
        $result = $this->service->calculateTaxEqualization(100000000.0, 20.0, 35.0);

        $this->assertEquals(20000000.00, $result['hypo_home_tax']);
        $this->assertEquals(35000000.00, $result['actual_host_tax']);
        $this->assertEquals(15000000.00, $result['company_borne_expense']);
        $this->assertEquals(80000000.00, $result['employee_net_retained']);
    }

    /**
     * (c) Entry playbook gate tak bisa dilewati bila syarat belum lengkap.
     */
    public function test_market_entry_playbook_gate_enforcement(): void
    {
        // Incomplete checklist -> ENTRY status, gate_passed = false
        $incomplete = $this->service->registerCountryExpansion('APAC', 'VN', 'Vietnam', [
            'permits' => true,
            'tax_id' => true,
            // missing labor_compliance and data_residency
        ]);
        $this->assertSame('ENTRY', $incomplete->status);
        $this->assertFalse((bool) $incomplete->gate_passed);

        // Complete checklist -> LIVE status, gate_passed = true
        $complete = $this->service->registerCountryExpansion('APAC', 'JP', 'Japan', [
            'permits' => true,
            'tax_id' => true,
            'labor_compliance' => true,
            'data_residency' => true,
        ]);
        $this->assertSame('LIVE', $complete->status);
        $this->assertTrue((bool) $complete->gate_passed);
    }

    /**
     * (e) group:audit / audit = 0 selisih.
     */
    public function test_global_command_audit_returns_zero_discrepancy(): void
    {
        $this->service->registerCountryExpansion('APAC', 'TH', 'Thailand', [
            'permits' => true,
            'tax_id' => true,
            'labor_compliance' => true,
            'data_residency' => true,
        ]);
        $this->service->recordFxExposure('AMERICAS', 'USD', 50000.0, 16000.0);

        $audit = $this->service->audit();
        $this->assertSame('HEALTHY', $audit['status']);
        $this->assertSame(0, $audit['discrepancy_count']);
    }
}
