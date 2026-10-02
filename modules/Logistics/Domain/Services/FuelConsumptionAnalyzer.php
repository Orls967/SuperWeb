<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services;

/**
 * Analisis konsumsi bahan bakar metode isi-penuh (full tank). Seluruh angka integer:
 * liter x 1000, jarak dalam meter, km/liter x 100, deviasi dalam basis poin (1 bp = 0,01%).
 */
class FuelConsumptionAnalyzer
{
    public const ANOMALY_THRESHOLD_BP = 3_000; // lebih dari 30%

    public const MIN_BASELINE_SAMPLES = 2;

    public const BASELINE_WINDOW = 5;

    /** km/l x 100 = jarak(m) x 100 / liter x 1000 */
    public function kmPerLiterX100(int $distanceM, int $litersX1000): int
    {
        if ($distanceM <= 0 || $litersX1000 <= 0) {
            return 0;
        }

        return intdiv($distanceM * 100 * 2 + $litersX1000, $litersX1000 * 2); // pembulatan half-up
    }

    public function totalCostIdr(int $litersX1000, int $pricePerLiterIdr): int
    {
        return intdiv($litersX1000 * $pricePerLiterIdr * 2 + 1000, 2000); // half-up
    }

    /**
     * @param  array<int, int>  $priorKmPerLiterX100  konsumsi log terdahulu yang tidak anomali (terbaru dulu)
     * @return array{baseline: int|null, deviation_bp: int|null, is_anomaly: bool, note: string|null}
     */
    public function evaluate(int $kmPerLiterX100, array $priorKmPerLiterX100): array
    {
        $samples = array_slice($priorKmPerLiterX100, 0, self::BASELINE_WINDOW);

        if (count($samples) < self::MIN_BASELINE_SAMPLES || $kmPerLiterX100 <= 0) {
            return ['baseline' => null, 'deviation_bp' => null, 'is_anomaly' => false, 'note' => null];
        }

        $baseline = intdiv(array_sum($samples) + intdiv(count($samples), 2), count($samples));
        $deviationBp = intdiv(($kmPerLiterX100 - $baseline) * 10_000, $baseline);
        $anomaly = abs($deviationBp) > self::ANOMALY_THRESHOLD_BP;

        $note = null;
        if ($anomaly) {
            $pct = number_format(abs($deviationBp) / 100, 1, ',', '.');
            $note = $deviationBp < 0
                ? "Konsumsi lebih boros {$pct}% dari rata-rata (indikasi kebocoran/penyalahgunaan BBM)."
                : "Konsumsi lebih irit {$pct}% dari rata-rata (periksa pencatatan odometer/liter).";
        }

        return ['baseline' => $baseline, 'deviation_bp' => $deviationBp, 'is_anomaly' => $anomaly, 'note' => $note];
    }
}
