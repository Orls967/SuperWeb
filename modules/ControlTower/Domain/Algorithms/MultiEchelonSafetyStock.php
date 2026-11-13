<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Algorithms;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Modules\ControlTower\Domain\Certificates\SafetyStockPolicyCertificate;
use Modules\Procurement\Contracts\SupplierLeadTimeStatistics;

/**
 * Algoritma I3: Optimasi Safety Stock Multi-Echelon & Klasifikasi ABC-XYZ.
 *
 * Menggunakan aritmetika integer berskala (tanpa float) untuk memadukan
 * variabilitas permintaan dan variabilitas waktu tunggu pemasok.
 */
final class MultiEchelonSafetyStock
{
    public const SCALE = 1000;

    /**
     * Faktor Z deterministik dalam basis points (10_000 = 1.00).
     *
     * @var array<string, int>
     */
    public const Z_FACTOR_BPS = [
        'AX' => 23_300,
        'AY' => 20_500,
        'AZ' => 19_600,
        'BX' => 16_500,
        'BY' => 16_500,
        'BZ' => 12_800,
        'CX' => 12_800,
        'CY' => 10_400,
        'CZ' => 8_400,
    ];

    /**
     * Pemetaan tingkat layanan target (basis points) per sel ABC-XYZ.
     *
     * @var array<string, int>
     */
    public const SERVICE_LEVEL_BPS = [
        'AX' => 9900,
        'AY' => 9800,
        'AZ' => 9750,
        'BX' => 9500,
        'BY' => 9500,
        'BZ' => 9000,
        'CX' => 9000,
        'CY' => 8500,
        'CZ' => 8000,
    ];

    /**
     * Bobot default pembagian stok pengaman antar echelon (total 10_000 bps).
     *
     * @var array<string, int>
     */
    public const DEFAULT_ECHELON_WEIGHTS = [
        'PLANT' => 2000,
        'DC' => 5000,
        'OUTLET' => 3000,
    ];

    public function __construct(
        private readonly ?SupplierLeadTimeStatistics $leadTimeStats = null,
    ) {}

    public function classifyAbc(int $cumulativeValueShareBps): string
    {
        return match (true) {
            $cumulativeValueShareBps <= 8000 => 'A',
            $cumulativeValueShareBps <= 9500 => 'B',
            default => 'C',
        };
    }

    public function classifyXyz(int $cvBps): string
    {
        return match (true) {
            $cvBps < 2500 => 'X',
            $cvBps <= 6500 => 'Y',
            default => 'Z',
        };
    }

    public function zFactorBpsFor(string $abcClass, string $xyzClass): int
    {
        $key = strtoupper($abcClass).strtoupper($xyzClass);
        if (! isset(self::Z_FACTOR_BPS[$key])) {
            throw new InvalidArgumentException("Kombinasi kelas ABC-XYZ tidak valid: {$key}");
        }

        return self::Z_FACTOR_BPS[$key];
    }

    /**
     * Klasifikasi ABC katalog berdasarkan pangsa nilai tahunan kumulatif (Pareto).
     *
     * @param  list<array{item_code: string, annual_usage_value_idr: int}>  $items
     * @return array<string, string> Map item_code => 'A'|'B'|'C'
     */
    public function classifyAbcCatalog(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $sorted = $items;
        usort($sorted, static function (array $a, array $b): int {
            $valCmp = $b['annual_usage_value_idr'] <=> $a['annual_usage_value_idr'];

            return $valCmp !== 0 ? $valCmp : strcmp($a['item_code'], $b['item_code']);
        });

        $totalValue = 0;
        foreach ($sorted as $item) {
            $totalValue += max(0, (int) $item['annual_usage_value_idr']);
        }

        $result = [];
        if ($totalValue <= 0) {
            foreach ($sorted as $item) {
                $result[$item['item_code']] = 'C';
            }

            return $result;
        }

        $cumulative = 0;
        foreach ($sorted as $index => $item) {
            $value = max(0, (int) $item['annual_usage_value_idr']);
            $cumulative += $value;
            $cumulativeBps = intdiv($cumulative * 10_000, $totalValue);

            if ($index === 0 && $value > 0 && $cumulativeBps > 8000) {
                $result[$item['item_code']] = 'A';
            } else {
                $result[$item['item_code']] = $this->classifyAbc($cumulativeBps);
            }
        }

        return $result;
    }

    /**
     * Menghitung safety stock multi-echelon dan ROP menggunakan rumus terpadu I3.
     *
     * @param  non-empty-list<int>  $demandHistory
     * @param  array<string, int>|null  $echelonWeights
     * @return array{
     *     item_code: string,
     *     abc_class: string,
     *     xyz_class: string,
     *     z_factor_bps: int,
     *     service_level_bps: int,
     *     cv_bps: int,
     *     mean_demand_milli: int,
     *     combined_std_dev_milli: int,
     *     lead_time_days: int,
     *     lead_time_sigma_days: int,
     *     safety_stock_qty: int,
     *     reorder_point_qty: int,
     *     echelon_breakdown: array<string, array{echelon_node: string, weight_bps: int, safety_stock_qty: int, reorder_point_qty: int}>,
     *     certificate: SafetyStockPolicyCertificate
     * }
     */
    public function calculate(
        string $itemCode,
        string $abcClass,
        array $demandHistory,
        int $leadTimeDays,
        int $leadTimeSigmaDays,
        ?array $echelonWeights = null,
    ): array {
        $abc = strtoupper(trim($abcClass));
        if ($itemCode === '' || ! in_array($abc, ['A', 'B', 'C'], true) || $demandHistory === [] || $leadTimeDays < 0 || $leadTimeSigmaDays < 0) {
            throw new InvalidArgumentException('INVALID_SAFETY_STOCK_INPUT: Parameter item, kelas ABC, historis permintaan, atau waktu tunggu tidak valid.');
        }

        $n = count($demandHistory);
        $sum = 0;
        foreach ($demandHistory as $qty) {
            if ($qty < 0) {
                throw new InvalidArgumentException('INVALID_SAFETY_STOCK_INPUT: Permintaan historis tidak boleh negatif.');
            }
            $sum += $qty;
        }

        $meanMilli = BigInteger::of($sum)->multipliedBy(self::SCALE)->dividedBy($n, RoundingMode::Down);

        $sumSqDiff = BigInteger::zero();
        foreach ($demandHistory as $qty) {
            $diff = BigInteger::of($qty)->multipliedBy(self::SCALE)->minus($meanMilli);
            $sumSqDiff = $sumSqDiff->plus($diff->multipliedBy($diff));
        }

        $demandVarMicro = $sumSqDiff->dividedBy($n, RoundingMode::Down);
        $demandStdDevMilli = $this->bigIsqrt($demandVarMicro);

        $cvBps = $meanMilli->isGreaterThan(0)
            ? $demandStdDevMilli->multipliedBy(10_000)->dividedBy($meanMilli, RoundingMode::Down)->toInt()
            : 10_000;

        $xyz = $this->classifyXyz($cvBps);
        $cellKey = $abc.$xyz;
        $zFactorBps = self::Z_FACTOR_BPS[$cellKey];
        $serviceLevelBps = self::SERVICE_LEVEL_BPS[$cellKey];

        // combinedVar = leadTimeDays * demandVar + meanDemand^2 * sigmaDays^2 (skala 1000^2)
        $termDemand = $demandVarMicro->multipliedBy($leadTimeDays);
        $sigmaSq = BigInteger::of($leadTimeSigmaDays)->multipliedBy($leadTimeSigmaDays);
        $termLeadTime = $meanMilli->multipliedBy($meanMilli)->multipliedBy($sigmaSq);
        $combinedVarMicro = $termDemand->plus($termLeadTime);
        $combinedStdDevMilli = $this->bigIsqrt($combinedVarMicro);

        // safety_stock_qty = ceil((Z / 10000) * (combinedStdDevMilli / 1000))
        $safetyStockQty = $combinedStdDevMilli->isGreaterThan(0)
            ? $combinedStdDevMilli->multipliedBy($zFactorBps)->dividedBy(10_000 * self::SCALE, RoundingMode::Ceiling)->toInt()
            : 0;

        $leadTimeDemandQty = $meanMilli->multipliedBy($leadTimeDays)->dividedBy(self::SCALE, RoundingMode::Ceiling)->toInt();
        $reorderPointQty = max($safetyStockQty, $leadTimeDemandQty + $safetyStockQty);

        $breakdown = $this->allocateEchelonBreakdown(
            safetyStockQty: $safetyStockQty,
            reorderPointQty: $reorderPointQty,
            weights: $echelonWeights,
        );

        $digest = hash('sha256', implode('|', [
            $itemCode,
            $abc,
            $xyz,
            (string) $serviceLevelBps,
            (string) $safetyStockQty,
            (string) $reorderPointQty,
        ]));

        return [
            'item_code' => $itemCode,
            'abc_class' => $abc,
            'xyz_class' => $xyz,
            'z_factor_bps' => $zFactorBps,
            'service_level_bps' => $serviceLevelBps,
            'cv_bps' => $cvBps,
            'mean_demand_milli' => $meanMilli->toInt(),
            'combined_std_dev_milli' => $combinedStdDevMilli->toInt(),
            'lead_time_days' => $leadTimeDays,
            'lead_time_sigma_days' => $leadTimeSigmaDays,
            'safety_stock_qty' => $safetyStockQty,
            'reorder_point_qty' => $reorderPointQty,
            'echelon_breakdown' => $breakdown,
            'certificate' => new SafetyStockPolicyCertificate(
                itemCode: $itemCode,
                abcClass: $abc,
                xyzClass: $xyz,
                serviceLevelBps: $serviceLevelBps,
                safetyStockQty: $safetyStockQty,
                reorderPointQty: $reorderPointQty,
                digest: $digest,
            ),
        ];
    }

    /**
     * Menghitung I3 dengan menarik statistik waktu tunggu pemasok dari Procurement.
     *
     * @param  non-empty-list<int>  $demandHistory
     * @param  array<string, int>|null  $echelonWeights
     * @return array{
     *     item_code: string,
     *     supplier_ref: string|null,
     *     lead_time_stats: array{mean_days:int, sigma_days:int, samples:int, on_time_permil:int},
     *     abc_class: string,
     *     xyz_class: string,
     *     z_factor_bps: int,
     *     service_level_bps: int,
     *     cv_bps: int,
     *     mean_demand_milli: int,
     *     combined_std_dev_milli: int,
     *     lead_time_days: int,
     *     lead_time_sigma_days: int,
     *     safety_stock_qty: int,
     *     reorder_point_qty: int,
     *     echelon_breakdown: array<string, array{echelon_node: string, weight_bps: int, safety_stock_qty: int, reorder_point_qty: int}>,
     *     certificate: SafetyStockPolicyCertificate
     * }
     */
    public function calculateWithSupplierStatistics(
        string $itemCode,
        string $abcClass,
        array $demandHistory,
        ?string $supplierRef = null,
        int $fallbackLeadTimeDays = 7,
        ?array $echelonWeights = null,
    ): array {
        $resolvedSupplierRef = $supplierRef;
        if ($resolvedSupplierRef === null && $this->leadTimeStats !== null) {
            $resolvedSupplierRef = $this->leadTimeStats->primarySupplierForItem($itemCode);
        }

        $stats = ($resolvedSupplierRef !== null && $this->leadTimeStats !== null)
            ? $this->leadTimeStats->forSupplierAndItem($resolvedSupplierRef, $itemCode)
            : ['mean_days' => 0, 'sigma_days' => 0, 'samples' => 0, 'on_time_permil' => 0];

        $leadTimeDays = $stats['samples'] > 0
            ? max(1, (int) $stats['mean_days'])
            : max(1, $fallbackLeadTimeDays);
        $sigmaDays = $stats['samples'] > 0 ? max(0, (int) $stats['sigma_days']) : 0;

        $calculated = $this->calculate(
            itemCode: $itemCode,
            abcClass: $abcClass,
            demandHistory: $demandHistory,
            leadTimeDays: $leadTimeDays,
            leadTimeSigmaDays: $sigmaDays,
            echelonWeights: $echelonWeights,
        );

        return array_merge($calculated, [
            'supplier_ref' => $resolvedSupplierRef,
            'lead_time_stats' => $stats,
        ]);
    }

    /**
     * @param  array<string, int>|null  $weights
     * @return array<string, array{echelon_node: string, weight_bps: int, safety_stock_qty: int, reorder_point_qty: int}>
     */
    private function allocateEchelonBreakdown(int $safetyStockQty, int $reorderPointQty, ?array $weights): array
    {
        $rawWeights = ($weights === null || $weights === [])
            ? self::DEFAULT_ECHELON_WEIGHTS
            : $weights;

        $filtered = [];
        foreach ($rawWeights as $node => $weight) {
            $cleanNode = strtoupper(trim((string) $node));
            if ($cleanNode !== '' && $weight > 0) {
                $filtered[$cleanNode] = (int) $weight;
            }
        }

        if ($filtered === []) {
            $filtered = self::DEFAULT_ECHELON_WEIGHTS;
        }

        $weightBps = $this->apportionInteger(10_000, $filtered);
        $ssAlloc = $this->apportionInteger($safetyStockQty, $filtered);
        $ropAlloc = $this->apportionInteger($reorderPointQty, $filtered);

        $breakdown = [];
        foreach ($filtered as $node => $_weight) {
            $breakdown[$node] = [
                'echelon_node' => $node,
                'weight_bps' => $weightBps[$node],
                'safety_stock_qty' => $ssAlloc[$node],
                'reorder_point_qty' => $ropAlloc[$node],
            ];
        }

        return $breakdown;
    }

    /**
     * Pembagian bilangan bulat Hamilton (largest remainder) agar jumlah tepat sama dengan total.
     *
     * @param  non-empty-array<string, int>  $weights
     * @return array<string, int>
     */
    private function apportionInteger(int $total, array $weights): array
    {
        $weightSum = array_sum($weights);
        $allocated = [];
        $remainders = [];

        if ($total <= 0 || $weightSum <= 0) {
            foreach ($weights as $node => $_weight) {
                $allocated[$node] = 0;
            }

            return $allocated;
        }

        $used = 0;
        foreach ($weights as $node => $weight) {
            $numerator = $total * $weight;
            $floorVal = intdiv($numerator, $weightSum);
            $allocated[$node] = $floorVal;
            $remainders[$node] = $numerator % $weightSum;
            $used += $floorVal;
        }

        $remaining = $total - $used;
        if ($remaining > 0) {
            $nodes = array_keys($weights);
            usort($nodes, static function (string $a, string $b) use ($remainders): int {
                return $remainders[$b] <=> $remainders[$a];
            });

            for ($i = 0; $i < $remaining; $i++) {
                $targetNode = $nodes[$i % count($nodes)];
                $allocated[$targetNode]++;
            }
        }

        return $allocated;
    }

    private function bigIsqrt(BigInteger $n): BigInteger
    {
        if ($n->isLessThanOrEqualTo(0)) {
            return BigInteger::zero();
        }

        $digits = strlen((string) $n);
        $x = BigInteger::of(10)->power(intdiv($digits + 1, 2));
        $y = $x->plus($n->dividedBy($x, RoundingMode::Down))->dividedBy(2, RoundingMode::Down);

        while ($y->isLessThan($x)) {
            $x = $y;
            $y = $x->plus($n->dividedBy($x, RoundingMode::Down))->dividedBy(2, RoundingMode::Down);
        }

        return $x;
    }
}
