<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Queries;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Mall\Domain\Models\FootfallCount;
use Modules\Mall\Domain\Models\TenantSalesReport;

/**
 * Analitik kunjungan mall. Semua angka diambil dengan agregasi di database
 * (SUM/GROUP BY) supaya halaman tetap ringan walau data 12 bulan.
 */
class FootfallAnalyticsQuery
{
    /**
     * @return array{
     *     daily: array<int, array{date: string, visitors: int}>,
     *     hourly: array<int, array{hour: int, visitors: int}>,
     *     by_gate: array<int, array{gate: string, visitors: int}>,
     *     total_visitors: int,
     *     avg_daily: int,
     *     peak_hour: ?int,
     *     busiest_date: ?string,
     *     tenant_transactions: int,
     *     conversion_rate: float
     * }
     */
    public function execute(int $propertyId, int $days = 30, ?Carbon $endDate = null): array
    {
        $endDate = ($endDate ?? Carbon::today())->copy()->startOfDay();
        $startDate = $endDate->copy()->subDays($days - 1);

        $daily = FootfallCount::query()
            ->where('property_id', $propertyId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('date')
            ->orderBy('date')
            ->get([
                'date',
                DB::raw('SUM(in_count) as visitors'),
            ])
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->toDateString(),
                'visitors' => (int) $row->visitors,
            ])
            ->all();

        $hourly = FootfallCount::query()
            ->where('property_id', $propertyId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get([
                'hour',
                DB::raw('SUM(in_count) as visitors'),
            ])
            ->map(fn ($row) => [
                'hour' => (int) $row->hour,
                'visitors' => (int) $row->visitors,
            ])
            ->all();

        $byGate = FootfallCount::query()
            ->where('property_id', $propertyId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('gate_name')
            ->orderByDesc(DB::raw('SUM(in_count)'))
            ->get([
                'gate_name',
                DB::raw('SUM(in_count) as visitors'),
            ])
            ->map(fn ($row) => [
                'gate' => (string) $row->gate_name,
                'visitors' => (int) $row->visitors,
            ])
            ->all();

        $totalVisitors = array_sum(array_column($daily, 'visitors'));
        $dayCount = max(1, count($daily));

        $peakHour = null;
        if ($hourly !== []) {
            $peakRow = collect($hourly)->sortByDesc('visitors')->first();
            $peakHour = $peakRow['hour'];
        }

        $busiestDate = null;
        if ($daily !== []) {
            $busiestDate = collect($daily)->sortByDesc('visitors')->first()['date'];
        }

        // Konversi memakai jumlah transaksi tenant terintegrasi pada periode yang tercakup
        $tenantTransactions = (int) TenantSalesReport::query()
            ->whereHas('lease', fn ($query) => $query->where('property_id', $propertyId))
            ->whereIn('period_month', $this->periodMonths($startDate, $endDate))
            ->sum('transaction_count');

        return [
            'daily' => $daily,
            'hourly' => $hourly,
            'by_gate' => $byGate,
            'total_visitors' => $totalVisitors,
            'avg_daily' => (int) round($totalVisitors / $dayCount),
            'peak_hour' => $peakHour,
            'busiest_date' => $busiestDate,
            'tenant_transactions' => $tenantTransactions,
            'conversion_rate' => $totalVisitors > 0
                ? round(($tenantTransactions / $totalVisitors) * 100, 2)
                : 0.0,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function periodMonths(Carbon $startDate, Carbon $endDate): array
    {
        $months = [];
        $cursor = $startDate->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($endDate)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $months;
    }
}
