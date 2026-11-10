<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Domain\Algorithms\TransferPricingMarkupCalculator;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\TransferPricingRule;
use Modules\Intercompany\Domain\Models\TransferPricingTrueUp;

class ExecuteTpTrueUpAdjustmentAction
{
    public function __construct(
        protected readonly TransferPricingMarkupCalculator $calculator,
        protected readonly IntercompanyLedgerPoster $ledgerPoster
    ) {}

    public function execute(
        string $fiscalYear,
        string $entityCode,
        string $category,
        int $actualCostIdr,
        int $actualRevenueIdr,
        ?int $userId = null
    ): TransferPricingTrueUp {
        $entity = IntercompanyEntity::where('entity_code', $entityCode)->first();
        $rule = TransferPricingRule::where('product_category', $category)->where('is_active', true)->first();

        if (! $rule) {
            throw new InvalidArgumentException("Aturan transfer pricing untuk kategori [{$category}] tidak ditemukan.");
        }

        $benchmarks = $rule->benchmarks()->pluck('operating_margin_bps')->toArray();
        if (empty($benchmarks)) {
            $minBps = $rule->min_margin_bps ?: 600;
            $maxBps = $rule->max_margin_bps ?: 1400;
            $medianBps = $rule->median_margin_bps ?: 1000;
            $benchmarks = [$minBps, (int) (($minBps + $medianBps) / 2), $medianBps, (int) (($medianBps + $maxBps) / 2), $maxBps];
        }

        $eval = $this->calculator->evaluateMargin(
            method: $rule->tp_method,
            costMinor: $actualCostIdr,
            actualPriceMinor: $actualRevenueIdr,
            benchmarkMarginsBps: $benchmarks
        );

        $trueUp = TransferPricingTrueUp::create([
            'fiscal_year' => $fiscalYear,
            'entity_id' => $entity?->id,
            'category' => $category,
            'actual_margin_bps' => $eval->actualMarginBps,
            'target_median_bps' => $eval->medianBps,
            'adjustment_amount_idr' => $eval->adjustmentMinor,
            'status' => 'adjusted',
        ]);

        return $trueUp;
    }
}
