<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Algorithms;

use InvalidArgumentException;
use Modules\Intercompany\Domain\Algorithms\Certificates\TransferPricingMarkupCertificate;
use Modules\Intercompany\Domain\DTOs\TransferPricingEvaluationDTO;

class TransferPricingMarkupCalculator
{
    /**
     * @param  array<int, int>  $benchmarkMarginsBps
     */
    public function evaluateMargin(
        string $method,
        int $costMinor,
        int $actualPriceMinor,
        array $benchmarkMarginsBps
    ): TransferPricingEvaluationDTO {
        if ($costMinor <= 0) {
            throw new InvalidArgumentException('Biaya produksi (cost) harus lebih besar dari nol.');
        }

        if (count($benchmarkMarginsBps) < 4) {
            throw new InvalidArgumentException('INSUFFICIENT_BENCHMARK_DATA: Data pembanding independen minimal 4 data.');
        }

        $margins = $benchmarkMarginsBps;
        sort($margins);
        $n = count($margins);

        // Kuartil 1, Median, Kuartil 3
        $q1Bps = $this->calculatePercentile($margins, 0.25);
        $medianBps = $this->calculatePercentile($margins, 0.50);
        $q3Bps = $this->calculatePercentile($margins, 0.75);

        // Margin aktual
        $actualMarginBps = (int) floor((($actualPriceMinor - $costMinor) * 10000) / $costMinor);

        $status = 'COMPLIANT';
        $adjustmentMinor = 0;
        $targetPriceMinor = $actualPriceMinor;

        if ($actualMarginBps < $q1Bps) {
            $status = 'BELOW_ARM_LENGTH';
            $targetPriceMinor = (int) ($costMinor + floor(($costMinor * $medianBps) / 10000));
            $adjustmentMinor = $targetPriceMinor - $actualPriceMinor;
        } elseif ($actualMarginBps > $q3Bps) {
            $status = 'ABOVE_ARM_LENGTH';
            $targetPriceMinor = (int) ($costMinor + floor(($costMinor * $medianBps) / 10000));
            $adjustmentMinor = $targetPriceMinor - $actualPriceMinor;
        }

        $certificate = new TransferPricingMarkupCertificate(
            method: $method,
            costMinor: $costMinor,
            actualPriceMinor: $actualPriceMinor,
            actualMarginBps: $actualMarginBps,
            q1Bps: $q1Bps,
            medianBps: $medianBps,
            q3Bps: $q3Bps,
            status: $status,
            adjustmentMinor: $adjustmentMinor,
            benchmarkCount: $n
        );

        return new TransferPricingEvaluationDTO(
            method: $method,
            costMinor: $costMinor,
            actualPriceMinor: $actualPriceMinor,
            actualMarginBps: $actualMarginBps,
            q1Bps: $q1Bps,
            medianBps: $medianBps,
            q3Bps: $q3Bps,
            status: $status,
            adjustmentMinor: $adjustmentMinor,
            targetPriceMinor: $targetPriceMinor,
            certificate: $certificate
        );
    }

    /**
     * @param  array<int, int>  $sorted
     */
    protected function calculatePercentile(array $sorted, float $p): int
    {
        $n = count($sorted);
        // Posisi 1-based: p * (n + 1)
        $pos = $p * ($n + 1);

        if ($pos <= 1) {
            return $sorted[0];
        }

        if ($pos >= $n) {
            return $sorted[$n - 1];
        }

        $idx = (int) floor($pos);
        $fraction = $pos - $idx;

        $val1 = $sorted[$idx - 1];
        $val2 = $sorted[$idx];

        return (int) ($val1 + floor($fraction * ($val2 - $val1)));
    }
}
