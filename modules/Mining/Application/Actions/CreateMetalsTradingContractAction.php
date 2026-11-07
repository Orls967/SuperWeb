<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\TradingContractStatus;
use Modules\Mining\Domain\Models\MiningMetalsTradingContract;

final class CreateMetalsTradingContractAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): MiningMetalsTradingContract
    {
        $counterparty = $attributes['counterparty_name'] ?? '';
        if (empty($counterparty)) {
            throw new InvalidArgumentException('Nama pihak lawan (counterparty_name) wajib diisi.');
        }

        $contractQuantity = (float) ($attributes['contract_quantity_metric_tons'] ?? 1000.0);
        if ($contractQuantity <= 0.0) {
            throw new InvalidArgumentException('Kuantitas kontrak harus lebih besar dari 0 ton.');
        }

        $fixedPriceCents = (int) ($attributes['fixed_price_usd_cents_per_ton'] ?? 1650000);
        $contractNum = $attributes['contract_number'] ?? ('CTR-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -6)));

        // Initial margin 12% in IDR ($1 = Rp 16.250)
        $notionalIdr = (int) round(($fixedPriceCents / 100.0) * $contractQuantity * 16250);
        $initialMarginIdr = (int) round($notionalIdr * 0.12);
        $maintenanceMarginIdr = (int) round($notionalIdr * 0.08);

        return MiningMetalsTradingContract::create([
            'contract_number' => $contractNum,
            'counterparty_name' => $counterparty,
            'metal_symbol' => $attributes['metal_symbol'] ?? 'LME-NI',
            'contract_type' => $attributes['contract_type'] ?? 'PHYSICAL_OFFTAKE',
            'contract_quantity_metric_tons' => $contractQuantity,
            'fixed_price_usd_cents_per_ton' => $fixedPriceCents,
            'pricing_basis' => $attributes['pricing_basis'] ?? 'LME_CASH_MONTHLY_AVG',
            'premium_discount_usd_cents_ton' => (int) ($attributes['premium_discount_usd_cents_ton'] ?? 0),
            'initial_margin_idr' => $initialMarginIdr,
            'maintenance_margin_idr' => $maintenanceMarginIdr,
            'delivery_period_start' => $attributes['delivery_period_start'] ?? now()->toDateString(),
            'delivery_period_end' => $attributes['delivery_period_end'] ?? now()->addMonths(3)->toDateString(),
            'trader_name' => $attributes['trader_name'] ?? 'Senior Metals Trader',
            'status' => TradingContractStatus::DRAFT->value,
            'hmac_signature' => hash('sha256', "CTR:{$contractNum}:{$contractQuantity}:{$fixedPriceCents}"),
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }
}
