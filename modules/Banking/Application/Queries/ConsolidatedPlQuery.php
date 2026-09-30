<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Queries;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConsolidatedPlQuery
{
    public const LINE_OTOMOTIF = 'otomotif';

    public const LINE_KEUANGAN = 'keuangan';

    public const LINE_KULINER = 'kuliner';

    public const LINE_PROPERTI = 'properti';

    /**
     * Jalankan query konsolidasi P&L seluruh lini bisnis dalam budget query minimal (maks 2-3 query).
     */
    public function execute(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        // 1. Agregasi Total Kumulatif per Akun Lini Bisnis
        $accountRows = DB::table('bank_ledger_entries as le')
            ->join('bank_ledger_accounts as la', 'le.account_id', '=', 'la.id')
            ->where('la.asset_code', 'IDR')
            ->where(function ($q) {
                $q->where('la.kind', 'revenue')
                    ->orWhere('la.kind', 'expense')
                    ->orWhere('la.code', 'like', 'revenue:%')
                    ->orWhere('la.code', 'like', 'expense:%')
                    ->orWhere('la.code', 'like', 'fee:%')
                    ->orWhere('la.code', 'like', 'fin:%');
            })
            ->selectRaw('la.id, la.code, la.name, la.kind, SUM(le.amount) as total_amount')
            ->groupBy('la.id', 'la.code', 'la.name', 'la.kind')
            ->get();

        $lines = [
            self::LINE_OTOMOTIF => [
                'key' => self::LINE_OTOMOTIF,
                'name' => 'Otomotif & Bengkel',
                'color' => '#3b82f6',
                'revenue' => 0,
                'expense' => 0,
                'net_profit' => 0,
                'margin_percent' => 0.0,
                'accounts' => [],
            ],
            self::LINE_KEUANGAN => [
                'key' => self::LINE_KEUANGAN,
                'name' => 'Keuangan & Perbankan',
                'color' => '#8b5cf6',
                'revenue' => 0,
                'expense' => 0,
                'net_profit' => 0,
                'margin_percent' => 0.0,
                'accounts' => [],
            ],
            self::LINE_KULINER => [
                'key' => self::LINE_KULINER,
                'name' => 'Kuliner RM Sari Ranah',
                'color' => '#f59e0b',
                'revenue' => 0,
                'expense' => 0,
                'net_profit' => 0,
                'margin_percent' => 0.0,
                'accounts' => [],
            ],
            self::LINE_PROPERTI => [
                'key' => self::LINE_PROPERTI,
                'name' => 'Properti Duta Mall',
                'color' => '#10b981',
                'revenue' => 0,
                'expense' => 0,
                'net_profit' => 0,
                'margin_percent' => 0.0,
                'accounts' => [],
            ],
        ];

        foreach ($accountRows as $row) {
            $code = (string) $row->code;
            $lineKey = $this->classifyAccountLine($code);
            if (! $lineKey || ! isset($lines[$lineKey])) {
                continue;
            }

            $rawAmount = (float) $row->total_amount;
            $isExpense = str_starts_with($code, 'expense:') || $row->kind === 'expense';

            if ($isExpense) {
                // Di ledger, debit expense bernilai negatif; nominal beban positif = abs(rawAmount)
                $expenseVal = (int) round(abs($rawAmount));
                $lines[$lineKey]['expense'] += $expenseVal;
                $lines[$lineKey]['accounts'][] = [
                    'code' => $code,
                    'name' => $row->name,
                    'type' => 'expense',
                    'amount' => $expenseVal,
                ];
            } else {
                // Pendapatan di ledger dicatat positif
                $revenueVal = (int) round($rawAmount);
                $lines[$lineKey]['revenue'] += $revenueVal;
                $lines[$lineKey]['accounts'][] = [
                    'code' => $code,
                    'name' => $row->name,
                    'type' => 'revenue',
                    'amount' => $revenueVal,
                ];
            }
        }

        $totalRevenue = 0;
        $totalExpense = 0;

        foreach ($lines as $k => &$line) {
            $line['net_profit'] = $line['revenue'] - $line['expense'];
            $line['margin_percent'] = $line['revenue'] > 0
                ? round(($line['net_profit'] / $line['revenue']) * 100, 1)
                : 0.0;

            $totalRevenue += $line['revenue'];
            $totalExpense += $line['expense'];
        }
        unset($line);

        $totalNetProfit = $totalRevenue - $totalExpense;
        $globalMargin = $totalRevenue > 0
            ? round(($totalNetProfit / $totalRevenue) * 100, 1)
            : 0.0;

        // 2. Trend Harian 30 Hari Terakhir
        $trendRows = DB::table('bank_ledger_entries as le')
            ->join('bank_ledger_accounts as la', 'le.account_id', '=', 'la.id')
            ->where('la.asset_code', 'IDR')
            ->where('le.created_at', '>=', $startDate)
            ->where(function ($q) {
                $q->where('la.kind', 'revenue')
                    ->orWhere('la.kind', 'expense')
                    ->orWhere('la.code', 'like', 'revenue:%')
                    ->orWhere('la.code', 'like', 'expense:%')
                    ->orWhere('la.code', 'like', 'fee:%')
                    ->orWhere('la.code', 'like', 'fin:%');
            })
            ->selectRaw('DATE(le.created_at) as date_key, la.code, la.kind, SUM(le.amount) as daily_amount')
            ->groupBy(DB::raw('DATE(le.created_at)'), 'la.code', 'la.kind')
            ->orderBy('date_key', 'asc')
            ->get();

        $dailyTrends = [];
        for ($i = $days; $i >= 0; $i--) {
            $d = Carbon::now()->subDays($i)->format('Y-m-d');
            $dailyTrends[$d] = [
                'date' => $d,
                'label' => Carbon::parse($d)->translatedFormat('d M'),
                self::LINE_OTOMOTIF => 0,
                self::LINE_KEUANGAN => 0,
                self::LINE_KULINER => 0,
                self::LINE_PROPERTI => 0,
                'total_revenue' => 0,
                'total_expense' => 0,
                'net_profit' => 0,
            ];
        }

        foreach ($trendRows as $tr) {
            $d = (string) $tr->date_key;
            if (! isset($dailyTrends[$d])) {
                continue;
            }

            $code = (string) $tr->code;
            $lineKey = $this->classifyAccountLine($code);
            if (! $lineKey) {
                continue;
            }

            $rawAmt = (float) $tr->daily_amount;
            $isExpense = str_starts_with($code, 'expense:') || $tr->kind === 'expense';

            if ($isExpense) {
                $exp = (int) round(abs($rawAmt));
                $dailyTrends[$d]['total_expense'] += $exp;
                $dailyTrends[$d][$lineKey] -= $exp;
            } else {
                $rev = (int) round($rawAmt);
                $dailyTrends[$d]['total_revenue'] += $rev;
                $dailyTrends[$d][$lineKey] += $rev;
            }
        }

        foreach ($dailyTrends as &$dt) {
            $dt['net_profit'] = $dt['total_revenue'] - $dt['total_expense'];
        }
        unset($dt);

        return [
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
                'net_profit' => $totalNetProfit,
                'margin_percent' => $globalMargin,
                'days' => $days,
            ],
            'lines' => $lines,
            'trends' => array_values($dailyTrends),
        ];
    }

    private function classifyAccountLine(string $code): ?string
    {
        if (str_starts_with($code, 'revenue:autoserve:') || str_starts_with($code, 'expense:autoserve:') ||
            str_starts_with($code, 'revenue:store:') || str_starts_with($code, 'expense:store:') ||
            str_starts_with($code, 'revenue:dex:')) {
            return self::LINE_OTOMOTIF;
        }

        if (str_starts_with($code, 'fee:banking:') || str_starts_with($code, 'fin:') ||
            str_starts_with($code, 'revenue:banking:') || str_starts_with($code, 'expense:banking:') ||
            str_starts_with($code, 'revenue:crypto:')) {
            return self::LINE_KEUANGAN;
        }

        if (str_starts_with($code, 'revenue:resto:') || str_starts_with($code, 'expense:resto:') ||
            str_starts_with($code, 'revenue:group:royalty:') || str_starts_with($code, 'expense:cogs:')) {
            return self::LINE_KULINER;
        }

        if (str_starts_with($code, 'revenue:mall:') || str_starts_with($code, 'expense:mall:')) {
            return self::LINE_PROPERTI;
        }

        return null;
    }
}
