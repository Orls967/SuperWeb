<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Egy\Domain\Models\SmartMeter;
use RuntimeException;

class EnergyGridAndMeteringService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function registerGenerationAsset(array $params): GenerationAsset
    {
        return GenerationAsset::create([
            'id' => (string) Str::uuid(),
            'asset_code' => $params['asset_code'],
            'name' => $params['name'],
            'asset_type' => $params['asset_type'],
            'installed_capacity_mw' => (float) $params['installed_capacity_mw'],
            'current_output_mw' => 0.0,
            'marginal_cost_per_mwh_minor' => (int) ($params['marginal_cost_per_mwh_minor'] ?? 0),
            'status' => 'ONLINE',
        ]);
    }

    public function registerSmartMeter(array $params): SmartMeter
    {
        return SmartMeter::create([
            'id' => (string) Str::uuid(),
            'meter_serial_number' => $params['meter_serial_number'] ?? 'SM-'.strtoupper(Str::random(10)),
            'consumer_property_type' => $params['consumer_property_type'],
            'consumer_property_id' => $params['consumer_property_id'],
            'grid_node_id' => $params['grid_node_id'] ?? null,
            'total_kwh_accumulated' => 0.0,
            'tariff_category' => $params['tariff_category'] ?? 'BUSINESS_TOU',
            'status' => 'ACTIVE',
        ]);
    }

    public function recordMeterReading(string $meterId, float $kwh, bool $isPeakHour, Carbon $start, Carbon $end): MeterReading
    {
        $meter = SmartMeter::findOrFail($meterId);

        // Time-of-use tariff: peak hour 2,100 IDR/kWh, off-peak 1,450 IDR/kWh
        $ratePerKwh = $isPeakHour ? 2100 : 1450;
        $totalCharge = (int) round($kwh * $ratePerKwh);

        return DB::transaction(function () use ($meter, $kwh, $isPeakHour, $ratePerKwh, $totalCharge, $start, $end) {
            $reading = MeterReading::create([
                'id' => (string) Str::uuid(),
                'meter_id' => $meter->id,
                'kwh_consumed' => $kwh,
                'is_peak_hour' => $isPeakHour,
                'rate_per_kwh_minor' => $ratePerKwh,
                'total_charge_minor' => $totalCharge,
                'interval_start' => $start,
                'interval_end' => $end,
            ]);

            $meter->increment('total_kwh_accumulated', $kwh);

            // Record utility receivable in ledger
            $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_METER_READING_BILL',
                description: "Smart meter consumption bill for {$meter->consumer_property_type}:{$meter->consumer_property_id}",
                idempotencyKey: 'EGY-MTR-'.$reading->id,
                entries: [
                    PostingEntryDTO::forCode('egy:utility_receivable:IDR', 'IDR', $totalCharge),
                    PostingEntryDTO::forCode('egy:grid_electricity_revenue:IDR', 'IDR', -$totalCharge),
                ],
                referenceType: 'METER_READING',
                referenceId: $reading->id,
            ));

            return $reading;
        });
    }

    /**
     * Merit-order dispatch: sort online generation assets by marginal cost and commit up to requested grid demand.
     */
    public function dispatchGridLoad(float $requiredLoadMw): Collection
    {
        $assets = GenerationAsset::where('status', 'ONLINE')
            ->orderBy('marginal_cost_per_mwh_minor', 'asc')
            ->get();

        $remainingLoad = $requiredLoadMw;
        $dispatched = collect();

        foreach ($assets as $asset) {
            if ($remainingLoad <= 0) {
                $asset->update(['current_output_mw' => 0.0]);

                continue;
            }

            $output = min($asset->installed_capacity_mw, $remainingLoad);
            $asset->update(['current_output_mw' => $output]);
            $remainingLoad -= $output;

            $dispatched->push([
                'asset_code' => $asset->asset_code,
                'asset_type' => $asset->asset_type,
                'dispatched_mw' => $output,
                'installed_capacity_mw' => $asset->installed_capacity_mw,
            ]);
        }

        if ($remainingLoad > 0) {
            throw new RuntimeException("Grid deficit: required load {$requiredLoadMw}MW exceeds total online generation capacity by {$remainingLoad}MW.");
        }

        return $dispatched;
    }

    public function settleNetMeteringPpa(array $params): PpaContract
    {
        $contractNumber = $params['contract_number'] ?? 'PPA-'.strtoupper(Str::random(8));
        $feedInTariff = (float) $params['feed_in_tariff_per_kwh_minor'];
        $surplusKwh = (float) $params['surplus_kwh_exported'];
        $settlementAmount = (int) round($surplusKwh * $feedInTariff);

        return DB::transaction(function () use ($params, $contractNumber, $feedInTariff, $surplusKwh, $settlementAmount) {
            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'ENERGY_PPA_NET_METERING_SETTLEMENT',
                description: "Net metering feed-in tariff credit for solar producer {$params['producer_entity_id']}",
                idempotencyKey: 'EGY-PPA-'.$contractNumber,
                entries: [
                    PostingEntryDTO::forCode('egy:power_purchase_expense:IDR', 'IDR', $settlementAmount),
                    PostingEntryDTO::forCode('egy:producer_payable:IDR', 'IDR', -$settlementAmount),
                ],
                referenceType: 'PPA_CONTRACT',
                referenceId: $contractNumber,
            ));

            return PpaContract::create([
                'id' => (string) Str::uuid(),
                'contract_number' => $contractNumber,
                'producer_entity_id' => $params['producer_entity_id'],
                'grid_offtaker_id' => $params['grid_offtaker_id'],
                'feed_in_tariff_per_kwh_minor' => $feedInTariff,
                'surplus_kwh_exported' => $surplusKwh,
                'total_settlement_minor' => $settlementAmount,
                'status' => 'ACTIVE',
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }
}
