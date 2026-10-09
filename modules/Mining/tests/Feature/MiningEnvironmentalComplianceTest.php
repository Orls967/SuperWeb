<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Banking\Domain\Models\LedgerTransaction;
use Modules\Mining\Application\Services\MiningEnvironmentalComplianceService;
use Modules\Mining\Domain\Models\MiningSite;
use RuntimeException;
use Tests\TestCase;

class MiningEnvironmentalComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected MiningEnvironmentalComplianceService $service;

    protected MiningSite $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MiningEnvironmentalComplianceService::class);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-ENV-01',
            'name' => 'Kaltim East Pit Ecological Center',
            'commodity' => 'COAL',
            'location' => 'East Kalimantan',
        ]);

        $accounts = [
            'min:reclamation_expense:IDR' => 'expense',
            'min:reclamation_provision_liability:IDR' => 'liability',
            'min:water_treatment_expense:IDR' => 'expense',
            'min:water_fee_payable:IDR' => 'liability',
            'min:environmental_penalty_expense:IDR' => 'expense',
            'min:environmental_penalty_payable:IDR' => 'liability',
        ];

        foreach ($accounts as $code => $kind) {
            LedgerAccount::create([
                'code' => $code,
                'name' => "Mining {$code}",
                'asset_code' => 'IDR',
                'kind' => $kind,
                'allow_negative' => true,
                'cached_balance' => '0',
            ]);
        }
    }

    public function test_124_1_and_124_6_a_reclamation_provision_lifecycle_and_ndvi_release(): void
    {
        // Accrue 500 million IDR provision for 50 hectares
        $provision = $this->service->accrueReclamationProvision($this->site->id, 50.0, 500000000);
        $this->assertEquals(500000000, $provision->provision_accrued_idr);
        $this->assertEquals(0, $provision->provision_released_idr);
        $this->assertEquals('ACCRUED', $provision->status);

        // NDVI below 0.60 threshold should be rejected
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NDVI vegetative growth');
        $this->service->releaseReclamationProvision($provision->id, 0.45);
    }

    public function test_124_1_and_124_6_a_reclamation_successful_release(): void
    {
        $provision = $this->service->accrueReclamationProvision($this->site->id, 20.0, 200000000);

        // Released with healthy NDVI 0.68
        $released = $this->service->releaseReclamationProvision($provision->id, 0.68);
        $this->assertEquals(200000000, $released->provision_released_idr);
        $this->assertEquals('RELEASED', $released->status);
        $this->assertNotNull($released->release_ledger_tx_id);

        $tx = LedgerTransaction::with('entries')->findOrFail($released->release_ledger_tx_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(2, $tx->entries->count());
    }

    public function test_124_2_and_124_6_b_d_water_monitoring_fee_and_penalty_accrual(): void
    {
        // 10,000 m3 water, acidic pH 4.8 (< 6.0 standard) and high TSS 350 mg/L (> 200) -> breach!
        $reading = $this->service->logWaterMonitoring([
            'site_id' => $this->site->id,
            'sampling_point' => 'OUTLET-SEDIMENT-POND-03',
            'water_volume_m3' => 10000.0,
            'effluent_ph' => 4.8,
            'effluent_tss_mg_l' => 350.0,
            'water_fee_rate_per_m3_minor' => 5000, // 50M IDR water fee
            'penalty_minor' => 75000000, // 75M IDR penalty
        ]);

        $this->assertTrue($reading->threshold_exceeded);
        $this->assertEquals(50000000, $reading->water_fee_minor);
        $this->assertEquals(75000000, $reading->environmental_penalty_minor);

        $tx = LedgerTransaction::with('entries')->findOrFail($reading->ledger_transaction_id);
        $sum = $tx->entries->sum('amount_minor');
        $this->assertEquals(0, $sum);
        $this->assertEquals(4, $tx->entries->count());
    }

    public function test_124_4_and_124_6_c_amdal_milestone_enforcement(): void
    {
        $unauthorizedSiteId = (string) Str::uuid();

        // Site without AMDAL must be rejected
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mandatory AMDAL license milestone not fulfilled');
        $this->service->assertSiteOperationAuthorized($unauthorizedSiteId);
    }

    public function test_124_4_and_124_6_c_amdal_approved_permits_operation(): void
    {
        $this->service->recordAmdalMilestone($this->site->id, 'AMDAL', 'SK-MENLHK-2026-8871');

        $authorized = $this->service->assertSiteOperationAuthorized($this->site->id);
        $this->assertTrue($authorized);
    }
}
