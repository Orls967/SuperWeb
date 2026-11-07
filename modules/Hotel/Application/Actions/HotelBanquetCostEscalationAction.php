<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use InvalidArgumentException;
use Modules\Hotel\Domain\Models\MiceContract;

class HotelBanquetCostEscalationAction
{
    /**
     * Mengevaluasi lonjakan harga bahan pangan terhadap kontrak BEO dan memeriksa pelanggaran Margin Guard 40%.
     *
     * @param  list<array{item_name: string, original_cost_idr: int, current_market_cost_idr: int, qty_units: int}>  $ingredients
     * @return array{contract_id: string, original_bom_total_idr: int, current_bom_total_idr: int, cost_inflation_pct: float, new_gross_margin_pct: float, is_margin_violated: bool, escalation_action: string}
     */
    public function execute(string $contractId, array $ingredients): array
    {
        $contract = MiceContract::findOrFail($contractId);
        $contractValue = (int) ($contract->contract_value_idr ?? 100000000);

        if ($contractValue <= 0) {
            throw new InvalidArgumentException('Nilai kontrak jamuan harus bernilai positif.');
        }

        $originalBom = 0;
        $currentBom = 0;

        foreach ($ingredients as $ing) {
            $qty = $ing['qty_units'] ?? 1;
            $originalBom += (($ing['original_cost_idr'] ?? 0) * $qty);
            $currentBom += (($ing['current_market_cost_idr'] ?? 0) * $qty);
        }

        $inflationPct = $originalBom > 0 ? round((($currentBom - $originalBom) / $originalBom) * 100, 2) : 0;
        $marginPct = round((($contractValue - $currentBom) / $contractValue) * 100, 2);

        $isViolated = $marginPct < 40.0;
        $escalationAction = $isViolated ? 'GM_OVERRIDE_REQUIRED' : 'MARGIN_PASSED';

        return [
            'contract_id' => $contract->id,
            'original_bom_total_idr' => $originalBom,
            'current_bom_total_idr' => $currentBom,
            'cost_inflation_pct' => $inflationPct,
            'new_gross_margin_pct' => $marginPct,
            'is_margin_violated' => $isViolated,
            'escalation_action' => $escalationAction,
        ];
    }
}
