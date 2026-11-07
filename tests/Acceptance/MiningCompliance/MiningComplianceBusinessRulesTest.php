<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningCompliance;

use Database\Seeders\LedgerAccountsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mining\Application\Actions\EvaluateAssayLabBiasAction;
use Modules\Mining\Application\Actions\RecordHashChainWeighbridgeTicketAction;
use Modules\Mining\Application\Actions\VerifyHighRiskWorkPermitAction;
use Modules\Mining\Application\Sagas\OfftakeShipmentLifecycleSaga;
use Modules\Mining\Domain\Models\MiningExternalFactor;
use Modules\Mining\Domain\Models\MiningOfftakeContract;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Tests\TestCase;

final class MiningComplianceBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_permit_rejected_and_double_dip_blocked_and_export_ban_reroutes_saga(): void
    {
        $this->seed(LedgerAccountsSeeder::class);

        $site = MiningSite::create([
            'site_code' => 'SITE-NICKEL-94',
            'name' => 'Konsesi Nikel Konawe 94',
            'commodity' => 'NICKEL',
            'location' => 'Konawe Utara',
            'timezone' => 'Asia/Makassar',
            'status' => 'ACTIVE',
        ]);

        $stockpile = MiningStockpile::create([
            'site_id' => $site->id,
            'stockpile_code' => 'STK-NI-94',
            'total_kg' => 1000000,
            'avg_grade_pct' => 1.8,
        ]);

        // 1. Tiket double-dip (plat sama dalam selang <= 60 detik) wajib ditolak (G7-D28)
        $ticketAction = app(RecordHashChainWeighbridgeTicketAction::class);
        $ticketAction->execute([
            'site_id' => (string) $site->id,
            'stockpile_id' => (string) $stockpile->id,
            'vehicle_plate' => 'DT-9499-NI',
            'gross_weight_kg' => 95000,
            'tare_weight_kg' => 35000,
            'weighed_at' => '2026-11-07 09:00:00',
            'destination' => 'STOCKPILE_IN',
        ]);

        try {
            $ticketAction->execute([
                'site_id' => (string) $site->id,
                'stockpile_id' => (string) $stockpile->id,
                'vehicle_plate' => 'DT-9499-NI',
                'gross_weight_kg' => 95000,
                'tare_weight_kg' => 35000,
                'weighed_at' => '2026-11-07 09:00:45',
                'destination' => 'STOCKPILE_IN',
            ]);
            $this->fail('Seharusnya menolak tiket double-dip dalam jendela 60 detik.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('double-dip', strtolower($e->getMessage()));
        }

        // 2. Izin kerja kedaluwarsa wajib ditolak (D94-5)
        $permit = MiningWorkPermit::create([
            'site_id' => $site->id,
            'permit_number' => 'WP-EXP-9401',
            'worker_id' => 'WRK-9401',
            'activity_type' => 'CONFINED_SPACE',
            'status' => 'active',
            'valid_from' => '2026-11-01 08:00:00',
            'valid_until' => '2026-11-02 08:00:00',
            'geofence_zone' => 'PIT-NORTH',
        ]);

        $permitAction = app(VerifyHighRiskWorkPermitAction::class);
        try {
            $permitAction->execute((string) $permit->id, '2026-11-07 10:00:00');
            $this->fail('Seharusnya menolak izin kerja yang sudah kedaluwarsa.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('kedaluwarsa', strtolower($e->getMessage()));
        }

        // 3. Bias assay lab vs umpire di atas toleransi memicu investigasi (D94-2)
        $biasAction = app(EvaluateAssayLabBiasAction::class);
        $biasResult = $biasAction->execute([
            'site_id' => (string) $site->id,
            'lot_code' => 'LOT-NI-9401',
            'internal_lab_grade_ppm' => 18500,
            'umpire_lab_grade_ppm' => 17100,
            'tolerance_ppm' => 500,
        ]);
        $this->assertTrue($biasResult['investigation_triggered']);
        $this->assertSame(1400, $biasResult['bias_ppm']);

        // 4. Kejutan larangan ekspor memicu kompensasi re-route ke smelter domestik (D94-7)
        MiningExternalFactor::create([
            'factor_key' => 'export_policy_NICKEL',
            'numeric_value_scaled' => 1,
            'unit' => 'boolean',
            'effective_from' => '2026-11-01',
            'version' => 1,
            'source' => 'Permen ESDM Larangan Ekspor Bijih Mentah',
            'label' => 'regulasi',
        ]);

        $contract = MiningOfftakeContract::create([
            'site_id' => $site->id,
            'contract_code' => 'OFT-EXP-SG-94',
            'buyer_party_ref' => 'BUYER-SG-01',
            'commodity' => 'NICKEL',
            'destination_type' => 'EXPORT',
            'contracted_tonnage_kg' => 55000000,
            'base_price_usd_cents' => 8500,
            'Benchmark_gar_kcal' => 5000,
            'status' => 'ACTIVE',
        ]);

        $offtakeSaga = app(OfftakeShipmentLifecycleSaga::class);
        $sagaOut = $offtakeSaga->execute([
            'contract_id' => (string) $contract->id,
            'shipment_code' => 'SHP-94-EXP-01',
            'quantity_kg' => 55000000,
            'actual_gar_kcal' => 4850,
            'ash_permil' => 93,
            'sulfur_permil' => 9,
            'shipment_date' => '2026-11-07',
        ]);

        $this->assertSame('REROUTED_DOMESTIC_SMELTER', $sagaOut['compensation_status']);
    }
}
