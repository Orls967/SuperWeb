<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Egy\Domain\Models\DistrictCoolingMeter;
use Modules\Egy\Domain\Models\EscoPerformanceContract;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Modules\Egy\Domain\Models\WaterDistribution;
use RuntimeException;

class DistrictUtilityAndEscoService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function recordWaterDistribution(array $params): WaterDistribution
    {
        $inputM3 = (float) $params['input_volume_m3'];
        $lossM3 = (float) ($params['leakage_loss_m3'] ?? 0.0);
        $deliveredM3 = (float) $params['delivered_volume_m3'];

        // Conservation check
        if (abs(($inputM3 - $lossM3) - $deliveredM3) > 0.01) {
            throw new RuntimeException("Water balance imbalance: input ({$inputM3}m3) - loss ({$lossM3}m3) != delivered ({$deliveredM3}m3).");
        }

        $tariffPerM3 = (int) $params['water_tariff_minor'];
        $waterCharge = (int) round($deliveredM3 * $tariffPerM3);

        return WaterDistribution::create([
            'id' => (string) Str::uuid(),
            'distribution_code' => 'WTR-DIST-'.strtoupper(Str::random(8)),
            'consumer_property_id' => $params['consumer_property_id'],
            'input_volume_m3' => $inputM3,
            'leakage_loss_m3' => $lossM3,
            'delivered_volume_m3' => $deliveredM3,
            'water_tariff_minor' => $tariffPerM3,
            'water_charge_minor' => $waterCharge,
        ]);
    }

    public function recordDistrictCoolingConsumption(string $buildingId, float $thermalKwh, int $ratePerThermalKwhMinor, float $cop = 4.5): DistrictCoolingMeter
    {
        $totalCharge = (int) round($thermalKwh * $ratePerThermalKwhMinor);

        return DistrictCoolingMeter::create([
            'id' => (string) Str::uuid(),
            'meter_code' => 'DCM-'.strtoupper(Str::random(8)),
            'building_property_id' => $buildingId,
            'thermal_kwh_consumed' => $thermalKwh,
            'cop_efficiency_factor' => $cop,
            'rate_per_thermal_kwh_minor' => $ratePerThermalKwhMinor,
            'total_charge_minor' => $totalCharge,
        ]);
    }

    public function issueConsolidatedInvoice(array $params): UtilityConsolidatedInvoice
    {
        $elec = (int) ($params['electricity_charge_minor'] ?? 0);
        $water = (int) ($params['water_charge_minor'] ?? 0);
        $cooling = (int) ($params['district_cooling_charge_minor'] ?? 0);
        $gas = (int) ($params['gas_charge_minor'] ?? 0);
        $total = $elec + $water + $cooling + $gas;

        $invoiceNumber = 'UTIL-INV-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($params, $elec, $water, $cooling, $gas, $total, $invoiceNumber) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_CONSOLIDATED_UTILITY_INVOICE',
                description: "Consolidated utility invoice {$invoiceNumber} for property {$params['property_id']}",
                idempotencyKey: 'EGY-INV-'.$invoiceNumber,
                entries: [
                    PostingEntryDTO::forCode('egy:utility_receivable:IDR', 'IDR', $total),
                    PostingEntryDTO::forCode('egy:utility_consolidated_revenue:IDR', 'IDR', -$total),
                ],
                referenceType: 'CONSOLIDATED_INVOICE',
                referenceId: $invoiceNumber,
            ));

            return UtilityConsolidatedInvoice::create([
                'id' => (string) Str::uuid(),
                'invoice_number' => $invoiceNumber,
                'property_id' => $params['property_id'],
                'billing_period' => $params['billing_period'],
                'electricity_charge_minor' => $elec,
                'water_charge_minor' => $water,
                'district_cooling_charge_minor' => $cooling,
                'gas_charge_minor' => $gas,
                'total_consolidated_minor' => $total,
                'status' => 'ISSUED',
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }

    public function settleEscoPerformanceContract(array $params): EscoPerformanceContract
    {
        $baseline = (int) $params['baseline_energy_cost_minor'];
        $actual = (int) $params['actual_energy_cost_minor'];
        $savings = max(0, $baseline - $actual);

        $escoSharePct = (float) ($params['esco_share_pct'] ?? 60.0);
        $escoRemuneration = (int) round($savings * ($escoSharePct / 100.0));
        $clientRetained = $savings - $escoRemuneration;

        $contractNumber = $params['contract_number'] ?? 'ESCO-'.strtoupper(Str::random(8));

        return DB::transaction(function () use ($params, $baseline, $actual, $savings, $escoSharePct, $escoRemuneration, $clientRetained, $contractNumber) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_ESCO_SAVINGS_SPLIT',
                description: "ESCO performance split remuneration for {$contractNumber}",
                idempotencyKey: 'EGY-ESCO-'.$contractNumber,
                entries: [
                    PostingEntryDTO::forCode('egy:esco_fee_receivable:IDR', 'IDR', $escoRemuneration),
                    PostingEntryDTO::forCode('egy:esco_service_revenue:IDR', 'IDR', -$escoRemuneration),
                ],
                referenceType: 'ESCO_CONTRACT',
                referenceId: $contractNumber,
            ));

            return EscoPerformanceContract::create([
                'id' => (string) Str::uuid(),
                'contract_number' => $contractNumber,
                'client_property_id' => $params['client_property_id'],
                'baseline_energy_cost_minor' => $baseline,
                'actual_energy_cost_minor' => $actual,
                'verified_savings_minor' => $savings,
                'esco_share_pct' => $escoSharePct,
                'esco_remuneration_minor' => $escoRemuneration,
                'client_retained_saving_minor' => $clientRetained,
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }
}
