<?php

declare(strict_types=1);

namespace Modules\Procurement\Infrastructure\Adapters;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Procurement\Contracts\SupplierLeadTimeStatistics;
use Modules\Supplier\Contracts\Supplier;

/**
 * Implementasi statistik waktu tunggu pemasok dari tabel pengadaan (prc_*)
 * dan kontrak pemasok (Modules\Supplier\Contracts\Supplier) tanpa melanggar A2.
 */
final class ProcurementSupplierLeadTimeStatistics implements SupplierLeadTimeStatistics
{
    private const VALID_GRN_STATUSES = ['accepted', 'inspected', 'open'];

    private const DEFAULT_PROMISED_DAYS = 7;

    /**
     * @return array{mean_days:int, sigma_days:int, samples:int, on_time_permil:int}
     */
    public function forSupplierAndItem(string $supplierRef, string $itemCode): array
    {
        if ($supplierRef === '' || $itemCode === '') {
            return $this->emptyStats();
        }

        $samples = $this->collectSamples(supplierRef: $supplierRef, itemCode: $itemCode);
        if ($samples === []) {
            return $this->emptyStats();
        }

        $computed = $this->computeMetrics($samples);

        return [
            'mean_days' => $computed['mean_days'],
            'sigma_days' => $computed['sigma_days'],
            'samples' => $computed['samples'],
            'on_time_permil' => $computed['on_time_permil'],
        ];
    }

    public function primarySupplierForItem(string $itemCode): ?string
    {
        if ($itemCode === '') {
            return null;
        }

        $samples = $this->collectSamples(itemCode: $itemCode);
        if ($samples === []) {
            return null;
        }

        /** @var array<string, int> $countsBySupplier */
        $countsBySupplier = [];
        foreach ($samples as $sample) {
            $ref = $sample['supplier_ref'];
            $countsBySupplier[$ref] = ($countsBySupplier[$ref] ?? 0) + 1;
        }

        $bestRef = null;
        $bestCount = -1;
        foreach ($countsBySupplier as $ref => $count) {
            if ($count > $bestCount || ($count === $bestCount && $bestRef !== null && strcmp($ref, $bestRef) < 0)) {
                $bestRef = $ref;
                $bestCount = $count;
            }
        }

        return $bestRef;
    }

    /**
     * @return list<array{supplier_ref:string, supplier_name:string, item_code:string, mean_days:int, sigma_days:int, samples:int, on_time_permil:int}>
     */
    public function allStatistics(): array
    {
        $samples = $this->collectSamples();
        if ($samples === []) {
            return [];
        }

        /** @var array<string, list<array{supplier_ref:string, item_code:string, lead_days:int, on_time:bool, received_date:string}>> $grouped */
        $grouped = [];
        $supplierRefs = [];

        foreach ($samples as $sample) {
            $key = $sample['supplier_ref']."\0".$sample['item_code'];
            $grouped[$key][] = $sample;
            $supplierRefs[$sample['supplier_ref']] = true;
        }

        /** @var array<string, string> $supplierNames */
        $supplierNames = Supplier::query()
            ->whereIn('id', array_keys($supplierRefs))
            ->pluck('name', 'id')
            ->map(static fn ($name): string => (string) $name)
            ->all();

        $results = [];
        foreach ($grouped as $groupSamples) {
            $first = $groupSamples[0];
            $ref = $first['supplier_ref'];
            $metrics = $this->computeMetrics($groupSamples);

            $results[] = [
                'supplier_ref' => $ref,
                'supplier_name' => $supplierNames[$ref] ?? $ref,
                'item_code' => $first['item_code'],
                'mean_days' => $metrics['mean_days'],
                'sigma_days' => $metrics['sigma_days'],
                'samples' => $metrics['samples'],
                'on_time_permil' => $metrics['on_time_permil'],
            ];
        }

        usort($results, static function (array $a, array $b): int {
            $cmp = strcmp($a['supplier_ref'], $b['supplier_ref']);

            return $cmp !== 0 ? $cmp : strcmp($a['item_code'], $b['item_code']);
        });

        return $results;
    }

    /**
     * @return list<array{period:string, mean_days:int, on_time_permil:int, samples:int}>
     */
    public function monthlyHistory(string $supplierRef, int $months, string $asOfDate): array
    {
        if ($supplierRef === '' || $months <= 0) {
            return [];
        }

        $cutoff = CarbonImmutable::parse($asOfDate)->toDateString();
        $samples = $this->collectSamples(supplierRef: $supplierRef, asOfDate: $cutoff);
        if ($samples === []) {
            return [];
        }

        /** @var array<string, list<array{supplier_ref:string, item_code:string, lead_days:int, on_time:bool, received_date:string}>> $byPeriod */
        $byPeriod = [];
        foreach ($samples as $sample) {
            $period = substr($sample['received_date'], 0, 7);
            $byPeriod[$period][] = $sample;
        }

        krsort($byPeriod);
        $latestMonths = array_slice($byPeriod, 0, $months, true);
        ksort($latestMonths);

        $history = [];
        foreach ($latestMonths as $period => $periodSamples) {
            $metrics = $this->computeMetrics($periodSamples);
            $history[] = [
                'period' => (string) $period,
                'mean_days' => $metrics['mean_days'],
                'on_time_permil' => $metrics['on_time_permil'],
                'samples' => $metrics['samples'],
            ];
        }

        return $history;
    }

    /**
     * @return list<array{supplier_ref:string, item_code:string, lead_days:int, on_time:bool, received_date:string}>
     */
    private function collectSamples(?string $supplierRef = null, ?string $itemCode = null, ?string $asOfDate = null): array
    {
        $query = DB::table('prc_receiving_lines as rl')
            ->join('prc_receiving_reports as grn', 'grn.id', '=', 'rl.grn_id')
            ->join('prc_po_lines as pl', 'pl.id', '=', 'rl.po_line_id')
            ->join('prc_purchase_orders as po', 'po.id', '=', 'grn.po_id')
            ->whereIn('grn.status', self::VALID_GRN_STATUSES)
            ->where('po.status', '!=', 'cancelled')
            ->whereNotNull('grn.received_at')
            ->select([
                'po.supplier_id',
                'po.expected_date',
                'po.created_at as po_created_at',
                'grn.received_at',
                'rl.description as rl_description',
                'pl.description as pl_description',
            ]);

        if ($supplierRef !== null) {
            $resolvedByCode = Supplier::query()->where('code', $supplierRef)->value('id');
            if ($resolvedByCode !== null) {
                $query->whereIn('po.supplier_id', [$supplierRef, (string) $resolvedByCode]);
            } else {
                $query->where('po.supplier_id', $supplierRef);
            }
        }

        if ($asOfDate !== null) {
            $query->whereDate('grn.received_at', '<=', $asOfDate);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $supplierIds = $rows->pluck('supplier_id')->unique()->filter()->values()->all();
        /** @var array<string, int> $supplierPromisedDays */
        $supplierPromisedDays = Supplier::query()
            ->whereIn('id', $supplierIds)
            ->pluck('lead_time_days', 'id')
            ->map(static fn ($days): int => max(0, (int) $days))
            ->all();

        $samples = [];
        foreach ($rows as $row) {
            $extractedCode = $this->extractItemCode(
                (string) ($row->rl_description ?? ''),
                (string) ($row->pl_description ?? ''),
            );

            if ($extractedCode === '') {
                continue;
            }

            if ($itemCode !== null) {
                $rawDesc = trim((string) ($row->rl_description ?? '')) !== ''
                    ? trim((string) $row->rl_description)
                    : trim((string) ($row->pl_description ?? ''));
                if ($extractedCode !== $itemCode && $rawDesc !== $itemCode) {
                    continue;
                }
                $extractedCode = $itemCode;
            }

            $receivedDateStr = CarbonImmutable::parse((string) $row->received_at)->toDateString();
            $receivedDt = new DateTimeImmutable($receivedDateStr);

            $expectedDt = $row->expected_date !== null
                ? new DateTimeImmutable(CarbonImmutable::parse((string) $row->expected_date)->toDateString())
                : null;
            $poCreatedDt = $row->po_created_at !== null
                ? new DateTimeImmutable(CarbonImmutable::parse((string) $row->po_created_at)->toDateString())
                : null;

            $leadDays = $this->resolveLeadDays($receivedDt, $expectedDt, $poCreatedDt);
            $promisedDays = $supplierPromisedDays[(string) $row->supplier_id] ?? self::DEFAULT_PROMISED_DAYS;
            $onTime = $expectedDt !== null
                ? $this->diffDays($expectedDt, $receivedDt) <= 0
                : $leadDays <= $promisedDays;

            $samples[] = [
                'supplier_ref' => (string) $row->supplier_id,
                'item_code' => $extractedCode,
                'lead_days' => $leadDays,
                'on_time' => $onTime,
                'received_date' => $receivedDateStr,
            ];
        }

        return $samples;
    }

    private function resolveLeadDays(
        DateTimeImmutable $receivedDt,
        ?DateTimeImmutable $expectedDt,
        ?DateTimeImmutable $poCreatedDt,
    ): int {
        if ($poCreatedDt !== null && $poCreatedDt <= $receivedDt) {
            return max(0, $this->diffDays($poCreatedDt, $receivedDt));
        }

        if ($expectedDt !== null) {
            return max(0, $this->diffDays($expectedDt, $receivedDt));
        }

        return 0;
    }

    private function diffDays(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->format('%r%a');
    }

    private function extractItemCode(string $rlDesc, string $plDesc): string
    {
        $raw = trim($rlDesc) !== '' ? trim($rlDesc) : trim($plDesc);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^([^\s]+?)(?:\s*(?:—|\|)\s+|\s+-\s+|:\s+)/u', $raw, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^([A-Z0-9][A-Z0-9._-]*[0-9_-][A-Z0-9._-]*)\s+/i', $raw, $matches) === 1) {
            return $matches[1];
        }

        return $raw;
    }

    /**
     * @param  non-empty-list<array{lead_days:int, on_time:bool}>  $samples
     * @return array{mean_days:int, sigma_days:int, samples:int, on_time_permil:int}
     */
    private function computeMetrics(array $samples): array
    {
        $n = count($samples);
        $sum = 0;
        $onTimeCount = 0;

        foreach ($samples as $sample) {
            $sum += $sample['lead_days'];
            if ($sample['on_time']) {
                $onTimeCount++;
            }
        }

        $meanDays = intdiv($sum + intdiv($n, 2), $n);
        $meanMilli = intdiv($sum * 1000, $n);

        $sumSqDiffMicro = 0;
        foreach ($samples as $sample) {
            $diffMilli = ($sample['lead_days'] * 1000) - $meanMilli;
            $sumSqDiffMicro += $diffMilli * $diffMilli;
        }

        $varianceMicro = intdiv($sumSqDiffMicro, $n);
        $sigmaMilli = $this->isqrt($varianceMicro);
        $sigmaDays = intdiv($sigmaMilli + 500, 1000);
        $onTimePermil = intdiv($onTimeCount * 1000, $n);

        return [
            'mean_days' => $meanDays,
            'sigma_days' => $sigmaDays,
            'samples' => $n,
            'on_time_permil' => $onTimePermil,
        ];
    }

    private function isqrt(int $n): int
    {
        if ($n <= 0) {
            return 0;
        }

        $x = $n;
        $y = intdiv($x + 1, 2);
        while ($y < $x) {
            $x = $y;
            $y = intdiv($x + intdiv($n, $x), 2);
        }

        return $x;
    }

    /**
     * @return array{mean_days:int, sigma_days:int, samples:int, on_time_permil:int}
     */
    private function emptyStats(): array
    {
        return [
            'mean_days' => 0,
            'sigma_days' => 0,
            'samples' => 0,
            'on_time_permil' => 0,
        ];
    }
}
