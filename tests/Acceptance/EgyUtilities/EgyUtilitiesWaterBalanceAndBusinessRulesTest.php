<?php

declare(strict_types=1);

namespace Tests\Acceptance\EgyUtilities;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Egy\Application\Actions\DetectWaterLeakAndDispatchWorkOrderAction;
use Modules\Egy\Application\Actions\ProcessWasteIntakeAndWteGenerationAction;
use Modules\Egy\Application\Actions\SettleEscoPerformanceContractAction;
use Modules\Egy\Domain\Models\EscoContract;
use Modules\Egy\Domain\Models\WasteTreatmentFacility;
use Modules\Egy\Domain\Models\WaterNetwork;
use Tests\TestCase;

final class EgyUtilitiesWaterBalanceAndBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_water_leak_detection_triggers_work_order_and_esco_split_settlement(): void
    {
        $this->seed();

        // 1. Water distribution network & leak detection
        $network = WaterNetwork::create([
            'network_name' => 'DMA-North-Industrial',
            'zone_code' => 'ZONE-NORTH-01',
            'capacity_liters_per_day' => 100_000,
            'status' => 'operational',
        ]);

        $leakAction = app(DetectWaterLeakAndDispatchWorkOrderAction::class);

        // Simulasi anomali: SIV 100.000 liter, konsumsi terukur hanya 60.000 liter (kehilangan 40.000 liter / 40% > ambang batas 25%)
        $incident = $leakAction->execute(
            networkId: $network->id,
            systemInputVolumeL: 100_000,
            billedAuthorizedL: 60_000,
            apparentLossesL: 2_000,
            realLossesL: 38_000,
            idempotencyKey: 'EGY:LEAK:DMA-NORTH:2026-11-06'
        );

        $this->assertNotNull($incident);
        $this->assertSame('dispatched_work_order', $incident->status);
        $this->assertNotNull($incident->asset_work_order_id);
        $this->assertStringContainsString('PIPE_LEAK_ANOMALY', $incident->description);

        // 2. Waste Intake & Tipping Fee
        $facility = WasteTreatmentFacility::create([
            'facility_name' => 'Metro Biogas Digester',
            'facility_code' => 'WTE-METRO-01',
            'daily_capacity_tons' => 50,
            'biogas_yield_m3_per_ton' => 80,
            'status' => 'active',
        ]);

        $wasteAction = app(ProcessWasteIntakeAndWteGenerationAction::class);
        $intake = $wasteAction->execute(
            facilityId: $facility->id,
            tonnageKg: 10_000, // 10 ton
            wasteType: 'organic_food',
            tippingFeeRateIdrPerTon: 150_000,
            idempotencyKey: 'EGY:WASTE:INTAKE:2026-11-06-01'
        );

        $this->assertSame(1_500_000, $intake->total_tipping_fee_idr);
        $this->assertGreaterThan(0, $intake->projected_biogas_m3);

        // Verifikasi posting tipping fee
        $txWaste = LedgerTransaction::findOrFail((int) $intake->ledger_transaction_id);
        $entriesWaste = LedgerEntry::where('transaction_id', $txWaste->id)->get();
        $govAr = (int) $entriesWaste->filter(fn ($e) => $e->account->code === 'ar:egy:government:IDR')->sum(fn ($e) => abs((float) $e->amount));
        $this->assertEquals(1_500_000, $govAr);

        // 3. ESCO Performance Contract & Split Settlement
        $escoContract = EscoContract::create([
            'contract_number' => 'ESCO-2026-001',
            'facility_name' => 'Grand Plaza Tower',
            'client_party_id' => 301,
            'baseline_monthly_kwh' => 100_000,
            'baseline_tariff_idr' => 1_400,
            'esco_share_bps' => 7000, // 70%
            'status' => 'active',
        ]);

        $escoAction = app(SettleEscoPerformanceContractAction::class);
        $settlement = $escoAction->execute(
            contractId: $escoContract->id,
            periodMonth: '2026-11',
            cddFactorBps: 11_000, // 1.1 faktor cuaca
            actualKwh: 88_000,    // gross saving 22.000 kWh * 1400 = 30.800.000 Rp
            idempotencyKey: 'EGY:ESCO:SETTLE:2026-11:001'
        );

        $this->assertSame(30_800_000, $settlement->total_savings_idr);
        $this->assertSame(21_560_000, $settlement->esco_share_idr); // 70%
        $this->assertSame(9_240_000, $settlement->client_share_idr);

        // Verifikasi posting ESCO split
        $txEsco = LedgerTransaction::findOrFail((int) $settlement->ledger_transaction_id);
        $entriesEsco = LedgerEntry::where('transaction_id', $txEsco->id)->get();
        $escoClientAr = (int) $entriesEsco->filter(fn ($e) => $e->account->code === 'ar:egy:esco_client:301:IDR')->sum(fn ($e) => abs((float) $e->amount));
        $this->assertEquals(21_560_000, $escoClientAr);
    }
}
