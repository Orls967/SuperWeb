<?php

declare(strict_types=1);

namespace Modules\Egy\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\ChilledWaterReading;
use Modules\Egy\Domain\Models\Chiller;
use Modules\Egy\Domain\Models\DistrictCoolingPlant;
use Modules\Egy\Domain\Models\EscoContract;
use Modules\Egy\Domain\Models\EscoSettlement;
use Modules\Egy\Domain\Models\WasteIntake;
use Modules\Egy\Domain\Models\WasteTreatmentFacility;
use Modules\Egy\Domain\Models\WaterMeterReading;
use Modules\Egy\Domain\Models\WaterNetwork;
use Modules\Egy\Domain\Models\WaterNode;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;

final class EgyT1DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $ledgerService = app(LedgerService::class);

        // 1. Water Networks (10 DMA)
        for ($i = 1; $i <= 10; $i++) {
            $network = WaterNetwork::firstOrCreate(
                ['zone_code' => "DMA-ZONE-{$i}"],
                [
                    'network_name' => "District Metering Area #{$i}",
                    'capacity_liters_per_day' => 1_000_000 * $i,
                    'water_source_type' => $i % 2 === 0 ? 'wtp_ipa' : 'deep_well',
                    'status' => 'operational',
                ]
            );

            // Nodes per DMA
            for ($n = 1; $n <= 4; $n++) {
                $node = WaterNode::firstOrCreate(
                    ['node_code' => "NODE-Z{$i}-N{$n}"],
                    [
                        'network_id' => $network->id,
                        'node_type' => $n === 1 ? 'dma_inlet' : ($n === 4 ? 'dma_outlet' : 'junction'),
                        'elevation_m' => 15 * $n,
                        'normal_pressure_kpa' => 300 + ($n * 10),
                        'status' => 'active',
                    ]
                );

                // Telemetry reading
                WaterMeterReading::firstOrCreate(
                    [
                        'network_id' => $network->id,
                        'node_id' => $node->id,
                        'recorded_at' => $now->copy()->subHours($n),
                    ],
                    [
                        'flow_rate_lps' => 25 * $n,
                        'cumulative_volume_l' => 250_000 * $n,
                        'pressure_kpa' => 310 + $n,
                    ]
                );
            }
        }

        // 2. Waste-to-Energy Facilities
        $wasteFacility = WasteTreatmentFacility::firstOrCreate(
            ['facility_code' => 'WTE-METRO-01'],
            [
                'facility_name' => 'Metro Anaerobic Biogas Plant',
                'daily_capacity_tons' => 100,
                'biogas_yield_m3_per_ton' => 85,
                'effluent_bod_mg_l' => 18,
                'effluent_cod_mg_l' => 45,
                'status' => 'active',
            ]
        );

        EgyAccounts::ensureAccount('ar:egy:government:IDR', 'AR Government Tipping Fee', AccountKind::ASSET, 'IDR', true);
        EgyAccounts::ensureAccount('egy:tipping_fee_revenue:IDR', 'Waste Tipping Fee Revenue', AccountKind::REVENUE, 'IDR', false);

        for ($w = 1; $w <= 5; $w++) {
            $tons = 10 * $w;
            $tippingRate = 150_000;
            $totalFee = $tons * $tippingRate;

            $intake = WasteIntake::firstOrCreate(
                [
                    'facility_id' => $wasteFacility->id,
                    'vehicle_number' => "B-{$w}999-WTE",
                    'recorded_at' => $now->copy()->subDays($w)->startOfDay(),
                ],
                [
                    'waste_type' => 'organic_food',
                    'tonnage_kg' => $tons * 1000,
                    'tipping_fee_rate_idr_per_ton' => $tippingRate,
                    'total_tipping_fee_idr' => $totalFee,
                    'projected_biogas_m3' => $tons * 85,
                    'status' => 'settled',
                ]
            );

            // Ledger posting for tipping fee
            if (! $intake->ledger_transaction_id) {
                $postingLines = Posting::lines()
                    ->debit('ar:egy:government:IDR', $totalFee)
                    ->credit('egy:tipping_fee_revenue:IDR', $totalFee);

                $tx = $ledgerService->post(new PostingDTO(
                    type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                    description: "Waste tipping fee intake #{$intake->id}",
                    idempotencyKey: "POST-SEED-WTE-{$intake->id}",
                    entries: $postingLines->toEntries('IDR'),
                    referenceType: 'WASTE_INTAKE',
                    referenceId: (string) $intake->id
                ));

                $intake->update(['ledger_transaction_id' => (int) $tx->id]);
            }
        }

        // 3. Central District Cooling Plants & Chillers
        $coolingPlant = DistrictCoolingPlant::firstOrCreate(
            ['plant_code' => 'DCP-CENTRAL-01'],
            [
                'plant_name' => 'Kawasan Industri Central Chiller Plant',
                'total_refrigeration_tons' => 8000,
                'designed_cop_bps' => 520, // COP 5.2
                'status' => 'active',
            ]
        );

        for ($c = 1; $c <= 4; $c++) {
            $chiller = Chiller::firstOrCreate(
                [
                    'plant_id' => $coolingPlant->id,
                    'chiller_number' => "CH-0{$c}",
                ],
                [
                    'capacity_tons' => 2000,
                    'rated_power_kw' => 1350,
                    'refrigerant_type' => 'R134a',
                    'status' => 'running',
                ]
            );

            ChilledWaterReading::firstOrCreate(
                [
                    'plant_id' => $coolingPlant->id,
                    'chiller_id' => $chiller->id,
                    'recorded_at' => $now->copy()->subHours($c * 2),
                ],
                [
                    'supply_temp_c_milli' => 6500,
                    'return_temp_c_milli' => 12000,
                    'delta_temp_c_milli' => 5500,
                    'flow_rate_lps' => 120,
                    'thermal_kwh_th' => 30_000,
                    'electric_kwh' => 6_000,
                    'cop_bps' => 500,
                ]
            );
        }

        // 4. ESCO Contracts & Settlements
        EgyAccounts::ensureAccount('egy:esco_service_revenue:IDR', 'ESCO Service Revenue', AccountKind::REVENUE, 'IDR', false);

        for ($e = 1; $e <= 5; $e++) {
            $contract = EscoContract::firstOrCreate(
                ['contract_number' => "ESCO-2026-00{$e}"],
                [
                    'facility_name' => "Komersial Menara Multi-Utilitas #{$e}",
                    'client_party_id' => 300 + $e,
                    'baseline_monthly_kwh' => 100_000,
                    'baseline_tariff_idr' => 1400,
                    'esco_share_bps' => 7000,
                    'guaranteed_savings_kwh' => 15000,
                    'start_date' => '2026-01-01',
                    'end_date' => '2028-12-31',
                    'status' => 'measuring',
                ]
            );

            $settlement = EscoSettlement::firstOrCreate(
                [
                    'esco_contract_id' => $contract->id,
                    'period_month' => '2026-10',
                ],
                [
                    'baseline_kwh' => 100_000,
                    'cdd_factor_bps' => 11000,
                    'adjusted_baseline_kwh' => 110_000,
                    'actual_kwh' => 88_000,
                    'gross_savings_kwh' => 22_000,
                    'rate_idr_per_kwh' => 1400,
                    'total_savings_idr' => 30_800_000,
                    'esco_share_bps' => 7000,
                    'esco_share_idr' => 21_560_000,
                    'client_share_idr' => 9_240_000,
                    'status' => 'settled',
                ]
            );

            if (! $settlement->ledger_transaction_id) {
                $clientAr = EgyAccounts::getOrCreateEscoClientAr((string) $contract->client_party_id);
                $postingLines = Posting::lines()
                    ->debit($clientAr->code, 21_560_000)
                    ->credit('egy:esco_service_revenue:IDR', 21_560_000);

                $tx = $ledgerService->post(new PostingDTO(
                    type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                    description: "ESCO split settlement {$contract->contract_number}",
                    idempotencyKey: "POST-SEED-ESCO-{$settlement->id}",
                    entries: $postingLines->toEntries('IDR'),
                    referenceType: 'ESCO_SETTLEMENT',
                    referenceId: (string) $settlement->id
                ));

                $settlement->update(['ledger_transaction_id' => (int) $tx->id]);
            }
        }
    }
}
