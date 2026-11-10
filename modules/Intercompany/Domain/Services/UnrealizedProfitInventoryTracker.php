<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Intercompany\Application\Actions\EliminateUnrealizedInventoryProfitAction;
use Modules\Intercompany\Domain\Models\IntercompanyStockTransfer;
use Psr\Clock\ClockInterface;

/**
 * Tracker & Eliminator Laba Persediaan Belum Terealisasi (Unrealized Profit in Inventory) (52.3, 52.4).
 * Melacak arus perpindahan stok barang antar entitas afiliasi (downstream / upstream):
 * - Menghitung selisih harga transfer vs harga pokok (markup profit)
 * - Menentukan proporsi stok yang masih tersimpan di gudang anak perusahaan pada tanggal neraca
 * - Memosting cadangan laba belum terealisasi dan dampak pajak tangguhan (deferred tax) 22%
 * - Merealisasikan laba ketika barang terjual kepada pihak ketiga independen.
 */
class UnrealizedProfitInventoryTracker
{
    public function __construct(
        protected readonly ?EliminateUnrealizedInventoryProfitAction $eliminateAction = null,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * @return array{
     *     period: string,
     *     total_transfers_evaluated: int,
     *     total_unrealized_profit_idr: int,
     *     deferred_tax_asset_idr: int,
     *     records: list<array<string, mixed>>
     * }
     */
    public function evaluatePeriodInventory(
        string $period,
        int $unsoldRatioPermille = 40000, // Misal 40% stok masih tersisa di gudang pembeli pada akhir periode
        int $corporateTaxRateBps = 2200,   // Tarif PPh Badan 22%
        bool $postElimination = true,
        ?int $operatorId = null
    ): array {
        $transfers = IntercompanyStockTransfer::where('status', 'completed')->get();

        $totalUnrealizedProfit = 0;
        $records = [];

        foreach ($transfers as $st) {
            $unitCost = (int) $st->cost_price_idr;
            $unitTransfer = (int) $st->transfer_price_idr;
            $qty = (int) $st->quantity;

            $totalCost = $unitCost * $qty;
            $totalTransfer = $unitTransfer * $qty;
            $totalMarkup = max(0, $totalTransfer - $totalCost);

            // Porsi markup pada barang yang BELUM terjual ke pihak luar
            $unrealizedMarkup = (int) intdiv($totalMarkup * $unsoldRatioPermille, 100000);

            if ($unrealizedMarkup <= 0) {
                continue;
            }

            $totalUnrealizedProfit += $unrealizedMarkup;

            // Pajak tangguhan (Deferred Tax) = unrealized markup * 22%
            $deferredTax = (int) intdiv($unrealizedMarkup * $corporateTaxRateBps, 10000);

            $elimEntry = null;
            if ($postElimination && $this->eliminateAction !== null) {
                $elimEntry = $this->eliminateAction->execute([
                    'period' => $period,
                    'selling_entity' => $st->source_entity,
                    'buying_entity' => $st->destination_entity,
                    'unrealized_profit_idr' => $unrealizedMarkup,
                    'operator_id' => $operatorId,
                ]);
            }

            $records[] = [
                'transfer_code' => $st->transfer_code,
                'source_entity' => $st->source_entity,
                'destination_entity' => $st->destination_entity,
                'item_sku' => $st->item_sku,
                'quantity' => $qty,
                'total_cost_idr' => $totalCost,
                'total_transfer_idr' => $totalTransfer,
                'total_markup_idr' => $totalMarkup,
                'unsold_ratio_permille' => $unsoldRatioPermille,
                'unrealized_profit_idr' => $unrealizedMarkup,
                'deferred_tax_idr' => $deferredTax,
                'elimination_record_id' => $elimEntry?->id,
            ];
        }

        $totalDeferredTax = (int) intdiv($totalUnrealizedProfit * $corporateTaxRateBps, 10000);

        return [
            'period' => $period,
            'total_transfers_evaluated' => count($records),
            'total_unrealized_profit_idr' => $totalUnrealizedProfit,
            'deferred_tax_asset_idr' => $totalDeferredTax,
            'records' => $records,
        ];
    }
}
