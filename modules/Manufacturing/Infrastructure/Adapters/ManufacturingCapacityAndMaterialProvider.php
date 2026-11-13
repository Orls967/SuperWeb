<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Infrastructure\Adapters;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Contracts\CapacityAndMaterialProvider;

/**
 * Adaptor kapasitas pabrik, keseimbangan material, dan jadwal MPS berbasis tabel mfg_*.
 */
final class ManufacturingCapacityAndMaterialProvider implements CapacityAndMaterialProvider
{
    private const int BUCKET_DAYS = 7;

    private const int DEFAULT_WORK_HOURS_PER_BUCKET = 40;

    public function cumulativeByPeriod(string $itemCode, int $periods, string $asOfDate): array
    {
        if ($periods <= 0) {
            return [
                'capacity_cumulative' => [],
                'material_cumulative' => [],
            ];
        }

        $baseDate = CarbonImmutable::parse($asOfDate)->startOfDay();
        $bucketStarts = $this->bucketStarts($baseDate, $periods);
        $horizonEnd = $baseDate->addDays($periods * self::BUCKET_DAYS)->toDateString();

        $materialId = DB::table('mfg_materials')
            ->where('code', $itemCode)
            ->value('id');

        $materialCumulative = $this->computeMaterialCumulative(
            materialId: is_string($materialId) ? $materialId : null,
            periods: $periods,
            bucketStarts: $bucketStarts,
            horizonEnd: $horizonEnd,
        );

        $capacityCumulative = $this->computeCapacityCumulative(
            periods: $periods,
            bucketStarts: $bucketStarts,
            horizonEnd: $horizonEnd,
        );

        return [
            'capacity_cumulative' => $capacityCumulative,
            'material_cumulative' => $materialCumulative,
        ];
    }

    public function mpsReceipts(string $itemCode, int $periods, string $asOfDate): array
    {
        if ($periods <= 0) {
            return [];
        }

        $receipts = array_fill(0, $periods, 0);
        $materialId = DB::table('mfg_materials')
            ->where('code', $itemCode)
            ->value('id');

        if (! is_string($materialId)) {
            return $receipts;
        }

        $baseDate = CarbonImmutable::parse($asOfDate)->startOfDay();
        $bucketStarts = $this->bucketStarts($baseDate, $periods);
        $horizonEnd = $baseDate->addDays($periods * self::BUCKET_DAYS)->toDateString();

        $rows = DB::table('mfg_mps_lines as ml')
            ->join('mfg_mps_headers as mh', 'mh.id', '=', 'ml.header_id')
            ->where('mh.status', 'active')
            ->where('ml.material_id', $materialId)
            ->where('ml.period_start', '<', $horizonEnd)
            ->select('ml.period_start', 'ml.qty')
            ->get();

        foreach ($rows as $row) {
            $idx = $this->bucketIndex((string) $row->period_start, $bucketStarts);
            $receipts[$idx] += max(0, $this->decimalToInt((string) $row->qty));
        }

        return $receipts;
    }

    /**
     * @param  list<string>  $bucketStarts
     * @return list<int>
     */
    private function computeMaterialCumulative(
        ?string $materialId,
        int $periods,
        array $bucketStarts,
        string $horizonEnd,
    ): array {
        if ($materialId === null) {
            return array_fill(0, $periods, 0);
        }

        $balance = DB::table('mfg_material_balances')
            ->where('material_id', $materialId)
            ->first(['qty_on_hand', 'qty_reserved']);

        $onHand = $balance !== null ? $this->decimalToInt((string) $balance->qty_on_hand) : 0;
        $reserved = $balance !== null ? $this->decimalToInt((string) $balance->qty_reserved) : 0;
        $startingMaterial = max(0, $onHand - $reserved);

        $periodReceipts = array_fill(0, $periods, 0);
        $scheduledRows = DB::table('mfg_scheduled_receipts')
            ->where('material_id', $materialId)
            ->where('status', 'open')
            ->where('due_date', '<', $horizonEnd)
            ->select('due_date', 'qty')
            ->get();

        foreach ($scheduledRows as $row) {
            $idx = $this->bucketIndex((string) $row->due_date, $bucketStarts);
            $periodReceipts[$idx] += max(0, $this->decimalToInt((string) $row->qty));
        }

        $cumulative = [];
        $running = $startingMaterial;
        for ($i = 0; $i < $periods; $i++) {
            $running += $periodReceipts[$i];
            $cumulative[] = $running;
        }

        return $cumulative;
    }

    /**
     * @param  list<string>  $bucketStarts
     * @return list<int>
     */
    private function computeCapacityCumulative(int $periods, array $bucketStarts, string $horizonEnd): array
    {
        $workCenters = DB::table('mfg_work_centers')
            ->where('is_active', true)
            ->select('id', 'capacity_per_hour', 'efficiency_percent')
            ->get()
            ->keyBy('id');

        if ($workCenters->isEmpty()) {
            return array_fill(0, $periods, 0);
        }

        $latestRunId = DB::table('mfg_capacity_loads')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('run_id');

        $loadsByBucket = array_fill(0, $periods, []);
        if (is_string($latestRunId)) {
            $loads = DB::table('mfg_capacity_loads')
                ->where('run_id', $latestRunId)
                ->where('period_start', '<', $horizonEnd)
                ->select('work_center_id', 'period_start', 'load_minutes', 'capacity_minutes')
                ->get();

            foreach ($loads as $load) {
                $idx = $this->bucketIndex((string) $load->period_start, $bucketStarts);
                $loadsByBucket[$idx][] = $load;
            }
        }

        $cumulative = [];
        $running = 0;
        for ($i = 0; $i < $periods; $i++) {
            $periodUnits = 0;
            if ($loadsByBucket[$i] !== []) {
                foreach ($loadsByBucket[$i] as $load) {
                    $wc = $workCenters->get((string) $load->work_center_id);
                    if ($wc === null) {
                        continue;
                    }
                    $freeMinutes = max(0, (int) $load->capacity_minutes - (int) $load->load_minutes);
                    $capPerHour = max(0, (int) $wc->capacity_per_hour);
                    $eff = max(1, (int) $wc->efficiency_percent);
                    $periodUnits += intdiv($freeMinutes * $capPerHour * $eff, 6000);
                }
            } else {
                foreach ($workCenters as $wc) {
                    $capPerHour = max(0, (int) $wc->capacity_per_hour);
                    $eff = max(1, (int) $wc->efficiency_percent);
                    $periodUnits += intdiv(self::DEFAULT_WORK_HOURS_PER_BUCKET * $capPerHour * $eff, 100);
                }
            }
            $running += $periodUnits;
            $cumulative[] = $running;
        }

        return $cumulative;
    }

    /**
     * @return list<string>
     */
    private function bucketStarts(CarbonImmutable $baseDate, int $periods): array
    {
        $starts = [];
        for ($i = 0; $i < $periods; $i++) {
            $starts[] = $baseDate->addDays($i * self::BUCKET_DAYS)->toDateString();
        }

        return $starts;
    }

    /**
     * @param  list<string>  $bucketStarts
     */
    private function bucketIndex(string $date, array $bucketStarts): int
    {
        $dateOnly = CarbonImmutable::parse($date)->toDateString();
        $found = 0;
        foreach ($bucketStarts as $idx => $start) {
            if ($dateOnly >= $start) {
                $found = $idx;
            }
        }

        return $found;
    }

    private function decimalToInt(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        return BigDecimal::of($value)->toScale(0, RoundingMode::Down)->toInt();
    }
}
