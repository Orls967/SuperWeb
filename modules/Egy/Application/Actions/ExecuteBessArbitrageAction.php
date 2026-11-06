<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Egy\Domain\Models\BessAsset;
use Modules\Egy\Domain\Models\BessDispatch;
use Modules\Egy\Ledger\EgyAccounts;
use Modules\Egy\Ledger\EgyTransactionTypes;
use Modules\Shared\Domain\Algorithms\Utility\BatteryArbitrageOptimizer;

/**
 * Aksi Eksekusi Optimasi & Pembukuan Arbitrase Baterai BESS ke Ledger.
 */
final class ExecuteBessArbitrageAction
{
    public function __construct(
        private readonly BatteryArbitrageOptimizer $optimizer,
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * @param  list<int>  $hourlyTariffProfile  24 tarif IDR per kWh
     */
    public function execute(
        int $bessAssetId,
        array $hourlyTariffProfile,
        string $dispatchDate,
        string $idempotencyKey
    ): BessDispatch {
        $asset = BessAsset::findOrFail($bessAssetId);
        $dispatchNumber = sprintf('DISP-%s-%d', $dispatchDate, $asset->id);

        $existing = BessDispatch::where('dispatch_number', $dispatchNumber)->first();
        if ($existing !== null) {
            return $existing;
        }

        $optResult = $this->optimizer->optimize(
            hourlyTariffIdrPerKwh: $hourlyTariffProfile,
            capacityKwh: (int) $asset->capacity_kwh,
            maxPowerKw: (int) $asset->max_charge_power_kw,
            initialSocBps: (int) $asset->current_soc_bps
        );

        $ledgerTxId = null;

        // Bukukan transaksi double-entry jika terdapat perputaran energi
        if ($optResult->totalDischargedKwh > 0 || $optResult->totalChargedKwh > 0) {
            $arbitrageRevenueAccount = 'egy:bess_arbitrage_revenue:IDR';
            $energyCostAccount = 'egy:bess_energy_cost:IDR';
            $degradationExpenseAccount = 'egy:bess_degradation_expense:IDR';
            $replacementReserveAccount = 'egy:bess_replacement_reserve:IDR';
            $clearingAccount = EgyAccounts::customerReceivable('1');

            $lines = Posting::lines();

            // 1. Catat pendapatan discharging
            if ($optResult->totalDischargingRevenueIdr > 0) {
                $lines->debit($clearingAccount->code, $optResult->totalDischargingRevenueIdr)
                    ->credit($arbitrageRevenueAccount, $optResult->totalDischargingRevenueIdr);
            }

            // 2. Catat biaya pengisian charging
            if ($optResult->totalChargingCostIdr > 0) {
                $lines->debit($energyCostAccount, $optResult->totalChargingCostIdr)
                    ->credit($clearingAccount->code, $optResult->totalChargingCostIdr);
            }

            // 3. Catat beban degradasi sel baterai dan alokasi ke cadangan penggantian baterai
            if ($optResult->totalDegradationCostIdr > 0) {
                $lines->debit($degradationExpenseAccount, $optResult->totalDegradationCostIdr)
                    ->credit($replacementReserveAccount, $optResult->totalDegradationCostIdr);
            }

            $tx = $this->ledgerService->post(new PostingDTO(
                type: EgyTransactionTypes::EGY_GRID_BILLING->value,
                description: sprintf('Penyelesaian Arbitrase BESS %s (%d kWh)', $asset->bess_code, $optResult->totalDischargedKwh),
                idempotencyKey: $idempotencyKey,
                entries: $lines->toEntries('IDR'),
                referenceType: 'BESS_ARBITRAGE',
                referenceId: $dispatchNumber,
            ));

            $ledgerTxId = $tx->id;
        }

        // Perbarui SoC dan siklus baterai pada aset fisik
        $asset->current_soc_bps = $optResult->finalSocBps;
        $asset->cycle_count += 1;
        // Hitung estimasi penuaan State-of-Health (berkurang ~1 bps per siklus penuh)
        if ($asset->state_of_health_bps > 7000) {
            $asset->state_of_health_bps -= 1;
        }
        $asset->save();

        return BessDispatch::create([
            'dispatch_number' => $dispatchNumber,
            'bess_asset_id' => $asset->id,
            'dispatch_type' => 'arbitrage',
            'energy_kwh' => $optResult->totalDischargedKwh,
            'charging_cost_idr' => $optResult->totalChargingCostIdr,
            'discharging_revenue_idr' => $optResult->totalDischargingRevenueIdr,
            'degradation_cost_idr' => $optResult->totalDegradationCostIdr,
            'net_profit_idr' => $optResult->netArbitrageProfitIdr,
            'ledger_transaction_id' => $ledgerTxId,
            'certificate_hash' => $optResult->certificateHash,
        ]);
    }
}
