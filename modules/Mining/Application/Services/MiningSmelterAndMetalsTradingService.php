<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Mining\Domain\Models\MetalsTradingPosition;
use Modules\Mining\Domain\Models\MetalWarehouseReceipt;
use Modules\Mining\Domain\Models\OfftakerContract;
use Modules\Mining\Domain\Models\SmelterRun;
use RuntimeException;

class MiningSmelterAndMetalsTradingService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function recordSmelterRun(array $params): SmelterRun
    {
        $inputOreTonnage = (float) $params['input_ore_tonnage'];
        $inputGradePct = (float) $params['input_grade_pct'];
        $outputMetalTonnage = (float) $params['output_metal_tonnage'];

        // Mass conservation check: pure metal contained in input ore
        $theoreticalContainedMetal = $inputOreTonnage * ($inputGradePct / 100.0);
        if ($outputMetalTonnage > $theoreticalContainedMetal) {
            throw new RuntimeException("Mass conservation violation: output metal ({$outputMetalTonnage}t) exceeds contained metal in input ore ({$theoreticalContainedMetal}t).");
        }

        $recoveryRatePct = $theoreticalContainedMetal > 0 ? ($outputMetalTonnage / $theoreticalContainedMetal) * 100.0 : 0.0;
        $byproductSlagTonnage = (float) ($params['byproduct_slag_tonnage'] ?? 0.0);
        $byproductRevenueIdr = (int) ($params['byproduct_revenue_idr'] ?? 0);

        if ($byproductRevenueIdr > 0) {
            $this->ledgerService->post(new PostingDTO(
                type: 'MINING_BYPRODUCT_SALES',
                description: "By-product slag sales revenue for run {$params['run_code']}",
                idempotencyKey: 'MIN-BYP-'.$params['run_code'],
                entries: [
                    PostingEntryDTO::forCode('min:byproduct_receivable:IDR', 'IDR', $byproductRevenueIdr),
                    PostingEntryDTO::forCode('min:byproduct_sales_revenue:IDR', 'IDR', -$byproductRevenueIdr),
                ],
                referenceType: 'SMELTER_RUN',
                referenceId: $params['run_code'],
            ));
        }

        return SmelterRun::create([
            'id' => (string) Str::uuid(),
            'site_id' => $params['site_id'],
            'run_code' => $params['run_code'],
            'output_commodity' => $params['output_commodity'],
            'input_ore_tonnage' => $inputOreTonnage,
            'input_grade_pct' => $inputGradePct,
            'output_metal_tonnage' => $outputMetalTonnage,
            'recovery_rate_pct' => round($recoveryRatePct, 2),
            'byproduct_slag_tonnage' => $byproductSlagTonnage,
            'byproduct_revenue_idr' => $byproductRevenueIdr,
            'energy_kwh_per_ton' => (float) ($params['energy_kwh_per_ton'] ?? 0.0),
            'status' => 'COMPLETED',
        ]);
    }

    public function invoiceOfftakerContract(array $params): OfftakerContract
    {
        $tonnage = (float) $params['contracted_tonnage'];
        $baseLmeUsd = (float) $params['base_lme_price_usd_per_ton'];
        $premiumDiscountUsd = (float) ($params['premium_discount_usd'] ?? 0.0);
        $fxRateToIdr = (int) ($params['fx_rate_to_idr'] ?? 16000);

        // Effective price per ton
        $effectivePriceUsdPerTon = $baseLmeUsd + $premiumDiscountUsd;
        $totalUsd = $tonnage * $effectivePriceUsdPerTon;
        $totalAmountIdr = (int) round($totalUsd * $fxRateToIdr);

        return DB::transaction(function () use ($params, $tonnage, $baseLmeUsd, $premiumDiscountUsd, $totalAmountIdr) {
            $contractNumber = $params['contract_number'] ?? 'OFFTAKE-'.strtoupper(Str::random(8));

            $tx = $this->ledgerService->post(new PostingDTO(
                type: 'MINING_OFFTAKER_INVOICE',
                description: "LME-linked metals invoice for contract {$contractNumber}",
                idempotencyKey: 'MIN-INV-'.$contractNumber,
                entries: [
                    PostingEntryDTO::forCode('min:trade_receivable:IDR', 'IDR', $totalAmountIdr),
                    PostingEntryDTO::forCode('min:metal_sales_revenue:IDR', 'IDR', -$totalAmountIdr),
                ],
                referenceType: 'OFFTAKER_CONTRACT',
                referenceId: $contractNumber,
            ));

            return OfftakerContract::create([
                'id' => (string) Str::uuid(),
                'contract_number' => $contractNumber,
                'buyer_party_id' => $params['buyer_party_id'],
                'commodity' => $params['commodity'],
                'contracted_tonnage' => $tonnage,
                'base_lme_price_usd_per_ton' => $baseLmeUsd,
                'premium_discount_usd' => $premiumDiscountUsd,
                'invoiced_amount_minor' => $totalAmountIdr,
                'currency' => 'IDR',
                'settlement_status' => 'INVOICED',
                'ledger_transaction_id' => $tx->id,
            ]);
        });
    }

    public function openTradingPosition(array $params): MetalsTradingPosition
    {
        $tonnage = (float) $params['tonnage'];
        $entryPrice = (float) $params['entry_price_usd'];
        $currentMark = (float) ($params['current_mark_price_usd'] ?? $entryPrice);

        $posType = strtoupper($params['position_type']);
        $pnlUsd = $posType === 'LONG'
            ? ($currentMark - $entryPrice) * $tonnage
            : ($entryPrice - $currentMark) * $tonnage;

        return MetalsTradingPosition::create([
            'id' => (string) Str::uuid(),
            'desk_code' => $params['desk_code'],
            'commodity' => $params['commodity'],
            'position_type' => $posType,
            'tonnage' => $tonnage,
            'entry_price_usd' => $entryPrice,
            'current_mark_price_usd' => $currentMark,
            'unrealized_pnl_minor' => (int) round($pnlUsd * 100), // cents
            'status' => 'OPEN',
        ]);
    }

    public function markToMarketTradingPosition(string $positionId, float $newMarkPriceUsd): MetalsTradingPosition
    {
        $pos = MetalsTradingPosition::findOrFail($positionId);
        $pnlUsd = $pos->position_type === 'LONG'
            ? ($newMarkPriceUsd - $pos->entry_price_usd) * $pos->tonnage
            : ($pos->entry_price_usd - $newMarkPriceUsd) * $pos->tonnage;

        $pos->update([
            'current_mark_price_usd' => $newMarkPriceUsd,
            'unrealized_pnl_minor' => (int) round($pnlUsd * 100),
        ]);

        return $pos;
    }

    public function issueWarehouseReceipt(string $warehouseId, string $commodity, float $tonnage): MetalWarehouseReceipt
    {
        $receiptNumber = 'MWR-'.strtoupper(Str::random(10));
        $receiptHash = hash('sha256', "{$receiptNumber}:{$warehouseId}:{$commodity}:{$tonnage}");

        return MetalWarehouseReceipt::create([
            'id' => (string) Str::uuid(),
            'receipt_number' => $receiptNumber,
            'warehouse_id' => $warehouseId,
            'commodity' => $commodity,
            'stored_tonnage' => $tonnage,
            'receipt_hash' => $receiptHash,
            'is_collateralized' => false,
            'status' => 'ACTIVE',
        ]);
    }

    public function pledgeReceiptAsCollateral(string $receiptId, string $facilityId, int $financingAmountMinor): MetalWarehouseReceipt
    {
        $receipt = MetalWarehouseReceipt::findOrFail($receiptId);
        if ($receipt->is_collateralized) {
            throw new RuntimeException("Warehouse receipt {$receipt->receipt_number} is already pledged as collateral.");
        }

        $receipt->update([
            'is_collateralized' => true,
            'collateral_facility_id' => $facilityId,
            'financing_amount_minor' => $financingAmountMinor,
            'status' => 'PLEDGED',
        ]);

        return $receipt;
    }

    public function releaseReceiptCollateral(string $receiptId): MetalWarehouseReceipt
    {
        $receipt = MetalWarehouseReceipt::findOrFail($receiptId);
        $receipt->update([
            'is_collateralized' => false,
            'collateral_facility_id' => null,
            'status' => 'RELEASED',
        ]);

        return $receipt;
    }
}
