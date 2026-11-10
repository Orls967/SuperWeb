<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Algorithms\TransferPricingMarkupCalculator;
use Modules\Intercompany\Domain\DTOs\TransferPricingEvaluationDTO;
use Modules\Intercompany\Domain\Models\TransferPricingRule;

class ValidateArmsLengthMarginAction
{
    public function __construct(
        protected readonly TransferPricingMarkupCalculator $calculator
    ) {}

    public function execute(
        TransferPricingRule $rule,
        int $costMinor,
        int $actualPriceMinor
    ): TransferPricingEvaluationDTO {
        if ($costMinor <= 0) {
            throw new InvalidArgumentException('Biaya pokok transaksi harus lebih besar dari nol.');
        }

        $benchmarks = $rule->benchmarks()->pluck('operating_margin_bps')->toArray();
        if (empty($benchmarks)) {
            // Gunakan rentang aturan sebagai basis benchmark fallback
            $minBps = $rule->min_margin_bps ?: (int) ($rule->min_arms_length_margin_percent * 100);
            $maxBps = $rule->max_margin_bps ?: (int) ($rule->max_arms_length_margin_percent * 100);
            $medianBps = $rule->median_margin_bps ?: (int) (($minBps + $maxBps) / 2);
            $step = max(1, (int) (($maxBps - $minBps) / 4));
            $benchmarks = [
                $minBps,
                $minBps + $step,
                $medianBps,
                $maxBps - $step,
                $maxBps,
            ];
        }

        return $this->calculator->evaluateMargin(
            method: $rule->tp_method,
            costMinor: $costMinor,
            actualPriceMinor: $actualPriceMinor,
            benchmarkMarginsBps: $benchmarks
        );
    }
}
