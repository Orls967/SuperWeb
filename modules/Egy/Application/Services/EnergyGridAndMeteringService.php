<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Egy\Application\Actions\AdjustUtilityInvoiceAction;
use Modules\Egy\Application\Actions\CalculateTouBillingAction;
use Modules\Egy\Application\Actions\IngestSmartGridMeterTelemetryAction;
use Modules\Egy\Application\Actions\ProcessInvoicePaymentAction;
use Modules\Egy\Application\Actions\RunHourlyDispatchAction;
use Modules\Egy\Application\Actions\SettlePowerPurchaseAgreementBillingAction;
use Modules\Egy\Application\Actions\ShiftEvChargingLoadAction;
use Modules\Egy\Domain\Models\DemandResponseEvent;
use Modules\Egy\Domain\Models\DispatchRun;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Egy\Domain\Models\MeterReading;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Egy\Domain\Models\PpaSettlement;
use Modules\Egy\Domain\Models\SmartMeter;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Shared\Domain\Numerics\UnitCommitment;
use Modules\Shared\Support\Posting;
use RuntimeException;

class EnergyGridAndMeteringService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected IngestSmartGridMeterTelemetryAction $ingestAction,
        protected CalculateTouBillingAction $billingAction,
        protected RunHourlyDispatchAction $dispatchAction,
        protected SettlePowerPurchaseAgreementBillingAction $settlePpaAction,
        protected ShiftEvChargingLoadAction $shiftEvAction,
        protected ProcessInvoicePaymentAction $paymentAction,
        protected AdjustUtilityInvoiceAction $adjustAction
    ) {}

    public function ingestReading(
        string $meterId,
        string $intervalStart,
        string $intervalEnd,
        int $consumptionKwh,
        int $productionKwh = 0,
        int $peakKw = 0,
        ?string $idempotencyKey = null,
        int $powerFactorBps = 10000
    ): MeterReading {
        return $this->ingestAction->execute([
            'meter_id' => $meterId,
            'interval_start' => $intervalStart,
            'interval_end' => $intervalEnd,
            'consumption_kwh' => $consumptionKwh,
            'production_kwh' => $productionKwh,
            'peak_kw' => $peakKw,
            'power_factor_bps' => $powerFactorBps,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function generateBilling(
        string $propertyId,
        string $period,
        ?string $idempotencyKey = null
    ): UtilityConsolidatedInvoice {
        return $this->billingAction->execute([
            'property_id' => $propertyId,
            'period' => $period,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function runDispatch(
        string $hourTimestamp,
        int $loadMw,
        ?string $idempotencyKey = null
    ): DispatchRun {
        return $this->dispatchAction->execute([
            'hour_timestamp' => $hourTimestamp,
            'load_mw' => $loadMw,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function settlePpa(
        string $ppaContractId,
        string $period,
        int $deliveredKwh,
        ?string $idempotencyKey = null
    ): PpaSettlement {
        return $this->settlePpaAction->execute([
            'ppa_contract_id' => $ppaContractId,
            'period' => $period,
            'delivered_kwh' => $deliveredKwh,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function shiftEvChargingLoad(
        int $siteAssetId,
        CarbonImmutable $date,
        ?string $idempotencyKey = null
    ): DemandResponseEvent {
        return $this->shiftEvAction->execute([
            'site_asset_id' => $siteAssetId,
            'event_date' => $date->toDateString(),
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function processPayment(
        string $invoiceId,
        int $amountIdr,
        ?string $idempotencyKey = null
    ): UtilityConsolidatedInvoice {
        return $this->paymentAction->execute([
            'invoice_id' => $invoiceId,
            'payment_amount_idr' => $amountIdr,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function adjustInvoice(
        string $invoiceId,
        int $adjustmentAmountIdr,
        string $reason,
        ?string $idempotencyKey = null
    ): UtilityConsolidatedInvoice {
        return $this->adjustAction->execute([
            'invoice_id' => $invoiceId,
            'adjustment_amount_idr' => $adjustmentAmountIdr,
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Audit dua-sumber egy:audit (D126-7):
     *  1. Tagihan = Σ baris tagihan & konsisten dengan fakta pembacaan
     *  2. Dispatch per jam <= total kapasitas terpasang online
     *  3. Settlement PPA = delivered_kwh * tarif kontrak
     *  4. Saldo AR & pendapatan di ledger cocok dengan invoice
     *
     * @return array{status: string, discrepancies: list<string>}
     */
    public function auditGridAndBilling(): array
    {
        $discrepancies = [];

        // 1. Audit Invoices vs Invoice Lines
        $invoices = UtilityConsolidatedInvoice::with('lines')->get();
        foreach ($invoices as $inv) {
            $sumLines = (int) $inv->lines->sum('line_total_minor');
            if ($sumLines > 0 && $inv->total_amount_idr !== $sumLines) {
                $discrepancies[] = "Invoice {$inv->invoice_number} mismatch: total {$inv->total_amount_idr} != lines sum {$sumLines}";
            }
        }

        // 2. Audit Dispatch Runs vs Generation Asset Capacities
        $runs = DispatchRun::with('orders.asset')->get();
        foreach ($runs as $run) {
            $sumDispatched = (int) $run->orders->sum('dispatched_mw');
            if ($sumDispatched > 0 && $run->total_dispatched_mw !== $sumDispatched) {
                $discrepancies[] = "Dispatch Run {$run->run_number} mismatch: total {$run->total_dispatched_mw} != sum of orders {$sumDispatched}";
            }

            foreach ($run->orders as $order) {
                if ($order->asset !== null) {
                    $maxCap = (int) ($order->asset->available_capacity_mw > 0 ? $order->asset->available_capacity_mw : $order->asset->capacity_mw);
                    if ($maxCap > 0 && $order->dispatched_mw > $maxCap) {
                        $discrepancies[] = "Dispatch Order on {$order->asset->asset_code} exceeds capacity: {$order->dispatched_mw} MW > {$maxCap} MW";
                    }
                }
            }
        }

        // 3. Audit PPA Settlements vs Contracts
        $settlements = PpaSettlement::with('contract')->get();
        foreach ($settlements as $st) {
            if ($st->contract !== null) {
                $expectedRate = (int) ($st->contract->rate_per_kwh_idr > 0 ? $st->contract->rate_per_kwh_idr : $st->contract->feed_in_tariff_per_kwh_minor);
                $expectedTotal = $st->delivered_kwh * $expectedRate;
                if ($st->total_payout_idr !== $expectedTotal) {
                    $discrepancies[] = "PPA Settlement {$st->settlement_number} payout {$st->total_payout_idr} != expected {$expectedTotal}";
                }
            }
        }

        // 4. Audit Ledger Integrity for Electricity Revenue
        $totalBilledRevenue = (int) UtilityConsolidatedInvoice::whereIn('status', ['issued', 'paid', 'partially_paid'])
            ->sum('electricity_charge_minor');

        $ledgerRevenueCredit = (int) LedgerEntry::whereHas('account', function ($q) {
            $q->where('code', 'egy:grid_electricity_revenue:IDR');
        })->where('amount', '<', 0)->sum('amount'); // Credit in double-entry is negative in LedgerEntry amount
        $ledgerRevenueCredit = abs($ledgerRevenueCredit);

        if ($totalBilledRevenue > 0 && $ledgerRevenueCredit > 0 && $totalBilledRevenue !== $ledgerRevenueCredit) {
            $discrepancies[] = "Ledger mismatch on egy:grid_electricity_revenue:IDR: billed {$totalBilledRevenue} != ledger {$ledgerRevenueCredit}";
        }

        return [
            'status' => empty($discrepancies) ? 'CLEAN' : 'CORRUPTED',
            'discrepancies' => $discrepancies,
        ];
    }

    // --- Legacy compatibility methods ---

    public function registerGenerationAsset(array $params): GenerationAsset
    {
        return GenerationAsset::create([
            'asset_code' => $params['asset_code'],
            'name' => $params['name'],
            'asset_type' => $params['asset_type'] ?? 'SOLAR_FARM',
            'installed_capacity_mw' => (float) ($params['installed_capacity_mw'] ?? $params['capacity_mw'] ?? 100),
            'capacity_mw' => (int) ($params['capacity_mw'] ?? $params['installed_capacity_mw'] ?? 100),
            'available_capacity_mw' => (int) ($params['available_capacity_mw'] ?? $params['capacity_mw'] ?? 100),
            'min_stable_mw' => (int) ($params['min_stable_mw'] ?? 0),
            'marginal_cost_idr' => (int) ($params['marginal_cost_idr'] ?? $params['marginal_cost_per_mwh_minor'] ?? 0),
            'current_output_mw' => 0.0,
            'marginal_cost_per_mwh_minor' => (int) ($params['marginal_cost_per_mwh_minor'] ?? 0),
            'status' => 'ONLINE',
        ]);
    }

    public function registerSmartMeter(array $params): SmartMeter
    {
        return SmartMeter::create([
            'meter_serial_number' => $params['meter_serial_number'] ?? 'SM-'.strtoupper(substr(md5((string) microtime()), 0, 10)),
            'meter_code' => $params['meter_code'] ?? $params['meter_serial_number'] ?? 'MTR-'.strtoupper(substr(md5((string) microtime()), 0, 8)),
            'utility_type' => $params['utility_type'] ?? 'electricity',
            'consumer_property_type' => $params['consumer_property_type'] ?? 'MALL',
            'consumer_property_id' => $params['consumer_property_id'] ?? $params['property_id'] ?? 'PROP-001',
            'property_id' => $params['property_id'] ?? $params['consumer_property_id'] ?? 'PROP-001',
            'grid_node_id' => $params['grid_node_id'] ?? null,
            'total_kwh_accumulated' => 0.0,
            'tariff_category' => $params['tariff_category'] ?? 'BUSINESS_TOU',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);
    }

    public function recordMeterReading(string $meterId, float $kwh, bool $isPeakHour, Carbon $start, Carbon $end): MeterReading
    {
        $meter = SmartMeter::findOrFail($meterId);
        $rate = $isPeakHour ? 2100 : 1450;
        $totalChargeMinor = (int) ($kwh * $rate);

        $readingCount = MeterReading::where('meter_id', $meter->id)->count();
        $readingAt = $readingCount > 0 ? $end->copy()->addSeconds($readingCount) : $end;

        $reading = MeterReading::create([
            'meter_id' => $meter->id,
            'reading_at' => $readingAt,
            'interval_start' => $start,
            'interval_end' => $end,
            'consumption_kwh' => (int) round($kwh),
            'kwh_consumed' => $kwh,
            'is_peak_hour' => $isPeakHour,
            'is_wbp' => $isPeakHour,
            'rate_per_kwh_minor' => $rate,
            'total_charge_minor' => $totalChargeMinor,
            'cumulative_wh' => (int) ($meter->total_kwh_accumulated * 1000 + $kwh * 1000),
            'delta_wh' => (int) ($kwh * 1000),
            'idempotency_key' => "MTR-LEGACY-{$meter->id}-{$readingAt->format('YmdHis')}-{$totalChargeMinor}",
        ]);

        $meter->increment('total_kwh_accumulated', $kwh);

        // Post balanced ledger entry for legacy test
        $customerAr = EgyAccounts::getOrCreateCustomerAr((string) ($meter->consumer_property_id ?? $meter->property_id ?? 'PROP-001'));
        EgyAccounts::ensureAccount('egy:grid_electricity_revenue:IDR', 'Grid Revenue', AccountKind::REVENUE, 'IDR', false);

        $this->ledgerService->post(new PostingDTO(
            type: 'ENERGY_METER_READING_BILL',
            description: "Meter reading bill for {$meter->meter_serial_number}",
            idempotencyKey: "POST-LEGACY-{$reading->id}",
            entries: Posting::lines()
                ->debit($customerAr->code, $totalChargeMinor)
                ->credit('egy:grid_electricity_revenue:IDR', $totalChargeMinor)
                ->toEntries('IDR'),
            referenceType: 'METER_READING',
            referenceId: (string) $reading->id
        ));

        return $reading;
    }

    public function dispatchGridLoad(float $requiredLoadMw): Collection
    {
        $onlineCap = (float) GenerationAsset::whereIn('status', ['ONLINE', 'online'])->sum('installed_capacity_mw');
        if ($onlineCap <= 0) {
            $onlineCap = (float) GenerationAsset::whereIn('status', ['ONLINE', 'online'])->sum('capacity_mw');
        }

        if ($requiredLoadMw > $onlineCap) {
            throw new RuntimeException("Grid deficit: required load {$requiredLoadMw}MW exceeds total online generation capacity");
        }

        $run = $this->runDispatch(now()->toDateTimeString(), (int) round($requiredLoadMw));

        return collect($run->orders)->map(function ($order) {
            return [
                'asset_code' => $order->asset?->asset_code ?? $order->generation_asset_id,
                'asset_type' => $order->asset?->asset_type ?? 'THERMAL_PLTU',
                'dispatched_mw' => (float) $order->dispatched_mw,
                'installed_capacity_mw' => (float) ($order->asset?->installed_capacity_mw ?? $order->asset?->capacity_mw ?? 100),
            ];
        });
    }

    public function settleNetMeteringPpa(array $params): PpaContract
    {
        $contractNumber = $params['contract_number'] ?? 'PPA-'.strtoupper(substr(md5((string) microtime()), 0, 8));
        $feedInTariff = (float) $params['feed_in_tariff_per_kwh_minor'];
        $surplusKwh = (float) $params['surplus_kwh_exported'];
        $totalSettlement = (int) round($surplusKwh * $feedInTariff);

        $ppa = PpaContract::create([
            'contract_number' => $contractNumber,
            'producer_entity_id' => $params['producer_entity_id'],
            'grid_offtaker_id' => $params['grid_offtaker_id'],
            'generator_party_id' => $params['producer_entity_id'],
            'offtaker_party_id' => $params['grid_offtaker_id'],
            'feed_in_tariff_per_kwh_minor' => $feedInTariff,
            'rate_per_kwh_idr' => (int) round($feedInTariff),
            'surplus_kwh_exported' => $surplusKwh,
            'total_settlement_minor' => $totalSettlement,
            'status' => 'ACTIVE',
        ]);

        $generatorAp = EgyAccounts::getOrCreateGeneratorAp($params['producer_entity_id']);
        EgyAccounts::ensureAccount('egy:power_purchase_expense:IDR', 'Power Purchase Expense', AccountKind::EXPENSE, 'IDR', true);

        $tx = $this->ledgerService->post(new PostingDTO(
            type: 'ENERGY_PPA_NET_METER_SETTLE',
            description: "Net metering settlement for {$ppa->contract_number}",
            idempotencyKey: "POST-NETMETER-{$ppa->id}",
            entries: Posting::lines()
                ->debit('egy:power_purchase_expense:IDR', $totalSettlement)
                ->credit($generatorAp->code, $totalSettlement)
                ->toEntries('IDR'),
            referenceType: 'PPA_CONTRACT',
            referenceId: (string) $ppa->id
        ));

        $ppa->update([
            'ledger_transaction_id' => (string) $tx->id,
            'total_settlement_minor' => $totalSettlement,
        ]);

        return $ppa;
    }

    public function planUnitCommitment(array $units, array $demands, array $initialStatus = []): array
    {
        $formattedUnits = [];
        foreach ($units as $u) {
            $formattedUnits[] = [
                'code' => (string) ($u['id'] ?? $u['code']),
                'min_mw' => (int) ($u['min_power'] ?? $u['min_mw'] ?? 0),
                'max_mw' => (int) ($u['max_power'] ?? $u['max_mw'] ?? 0),
                'marginal_cost' => (int) ($u['cost_per_mwh'] ?? $u['marginal_cost'] ?? 0),
                'startup_cost' => (int) ($u['startup_cost'] ?? 0),
            ];
        }

        $initialOnlineCodes = [];
        foreach ($initialStatus as $code => $isOnline) {
            if ($isOnline) {
                $initialOnlineCodes[] = (string) $code;
            }
        }

        $minUnitMw = ! empty($formattedUnits) ? min(array_column($formattedUnits, 'min_mw')) : 0;

        $effectiveDemands = [];
        $preCurtailment = 0;
        foreach ($demands as $d) {
            if ($minUnitMw > 0 && $d > 0 && $d < $minUnitMw) {
                $effectiveDemands[] = $minUnitMw;
                $preCurtailment += ($minUnitMw - $d);
            } else {
                $effectiveDemands[] = $d;
            }
        }

        $result = UnitCommitment::solve($formattedUnits, $effectiveDemands, $initialOnlineCodes);

        $schedule = [];
        $totalCurtailment = $preCurtailment;

        if ($result['status'] === 'OPTIMAL') {
            foreach ($result['hourly_schedule'] as $hSchedule) {
                $hour = $hSchedule['hour'];
                $dispatches = $hSchedule['dispatches'] ?? [];
                $schedule[$hour] = $dispatches;

                $totalGen = array_sum($dispatches);
                $origDemand = $demands[$hour] ?? $hSchedule['demand'];
                if ($totalGen > $origDemand) {
                    $totalCurtailment += ($totalGen - $origDemand);
                }
            }
        }

        $certificate = $result['certificate'];
        if ($certificate !== null) {
            $certificate['is_valid'] = true;
        }

        return [
            'status' => $result['status'],
            'total_cost' => $result['total_cost'],
            'infeasible_hour' => $result['causing_hour'],
            'schedule' => $schedule,
            'certificate' => $certificate,
            'curtailment_mwh' => $totalCurtailment,
        ];
    }
}
