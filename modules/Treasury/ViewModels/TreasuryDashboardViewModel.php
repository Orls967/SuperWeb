<?php

declare(strict_types=1);

namespace Modules\Treasury\ViewModels;

use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankReconciliationUnmatched;
use Modules\Treasury\Domain\Models\CashForecast;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\FxLot;
use Modules\Treasury\Domain\Models\Revaluation;

/**
 * ViewModel Dasbor & Meja Kerja Operasional Treasury (Fase 48).
 *
 * Menyusun metrik likuiditas multi-valas, eksposur lindung nilai, kepatuhan covenant DER,
 * antrean rekonsiliasi ber-umur (aging), dan proyeksi kas 13 minggu secara deterministik
 * menggunakan aritmetika integer.
 */
final class TreasuryDashboardViewModel
{
    /**
     * Menghasilkan ringkasan komprehensif posisi perbendaharaan grup.
     *
     * @return array{
     *     active_currencies_count: int,
     *     cash_positions_by_currency: array<string, int>,
     *     total_functional_cash_idr: int,
     *     fx_subledger: array{
     *         active_lots_count: int,
     *         exhausted_lots_count: int,
     *         total_carrying_idr: int,
     *         latest_revaluation_gain_loss_idr: int
     *     },
     *     hedge_portfolio: array{
     *         total_contracts: int,
     *         active_contracts: int,
     *         matured_contracts: int,
     *         total_mtm_idr: int
     *     },
     *     credit_facilities: array{
     *         active_count: int,
     *         total_limit_idr: int,
     *         total_drawn_idr: int,
     *         total_available_idr: int,
     *         utilization_permil: int
     *     },
     *     reconciliation_queue: array{
     *         pending_unmatched_count: int,
     *         aged_over_7_days_count: int,
     *         max_aging_days: int
     *     },
     *     cash_pooling: array{
     *         pools_count: int,
     *         total_last_swept_idr: int
     *     },
     *     forecast_13w: array{
     *         weeks_count: int,
     *         min_closing_balance_idr: int,
     *         max_closing_balance_idr: int
     *     }
     * }
     */
    public function toArray(): array
    {
        $activeCurrenciesCount = Currency::where('is_active', true)->count();

        $accounts = BankAccount::where('is_active', true)->get();
        $positionsByCcy = [];
        $totalIdrCash = 0;

        foreach ($accounts as $acc) {
            $ccy = strtoupper((string) $acc->currency);
            $bal = (int) $acc->balance;
            $positionsByCcy[$ccy] = ($positionsByCcy[$ccy] ?? 0) + $bal;
            if ($ccy === 'IDR') {
                $totalIdrCash += $bal;
            }
        }

        $activeLotsCount = FxLot::where('status', 'active')->count();
        $exhaustedLotsCount = FxLot::where('status', 'exhausted')->count();
        $totalCarryingIdr = (int) FxLot::where('status', 'active')->sum('current_carrying_idr');
        $latestRevalGainLoss = (int) Revaluation::where('is_reversed', false)->sum('gain_loss_idr');

        $totalContracts = ForwardContract::count();
        $activeContracts = ForwardContract::where('status', HedgeStatus::ACTIVE->value)->count();
        $maturedContracts = ForwardContract::where('status', HedgeStatus::MATURED->value)->count();
        $totalMtmIdr = (int) ForwardContract::whereIn('status', [
            HedgeStatus::ACTIVE->value,
            HedgeStatus::BOOKED->value,
            HedgeStatus::MATURED->value,
        ])->sum('mtm_value_idr');

        $activeFacilities = CreditFacility::where('status', 'active')->get();
        $totalLimitIdr = (int) $activeFacilities->sum('credit_limit_idr');
        $totalDrawnIdr = (int) $activeFacilities->sum('drawn_amount_idr');
        $totalAvailableIdr = max(0, $totalLimitIdr - $totalDrawnIdr);
        $utilizationPermil = $totalLimitIdr > 0
            ? (int) intdiv($totalDrawnIdr * 1000, $totalLimitIdr)
            : 0;

        $pendingUnmatchedCount = BankReconciliationUnmatched::where('status', 'pending')->count();
        $agedOver7Days = BankReconciliationUnmatched::where('status', 'pending')
            ->where('aging_days', '>', 7)
            ->count();
        $maxAgingDays = (int) (BankReconciliationUnmatched::where('status', 'pending')->max('aging_days') ?? 0);

        $poolsCount = CashPool::count();
        $totalLastSweptIdr = (int) CashPool::sum('last_swept_amount_idr');

        $forecasts = CashForecast::where('scenario', 'base')->orderBy('week_number')->get();
        $minClosing = $forecasts->isNotEmpty() ? (int) $forecasts->min('closing_balance_idr') : 0;
        $maxClosing = $forecasts->isNotEmpty() ? (int) $forecasts->max('closing_balance_idr') : 0;

        return [
            'active_currencies_count' => $activeCurrenciesCount,
            'cash_positions_by_currency' => $positionsByCcy,
            'total_functional_cash_idr' => $totalIdrCash,
            'fx_subledger' => [
                'active_lots_count' => $activeLotsCount,
                'exhausted_lots_count' => $exhaustedLotsCount,
                'total_carrying_idr' => $totalCarryingIdr,
                'latest_revaluation_gain_loss_idr' => $latestRevalGainLoss,
            ],
            'hedge_portfolio' => [
                'total_contracts' => $totalContracts,
                'active_contracts' => $activeContracts,
                'matured_contracts' => $maturedContracts,
                'total_mtm_idr' => $totalMtmIdr,
            ],
            'credit_facilities' => [
                'active_count' => $activeFacilities->count(),
                'total_limit_idr' => $totalLimitIdr,
                'total_drawn_idr' => $totalDrawnIdr,
                'total_available_idr' => $totalAvailableIdr,
                'utilization_permil' => $utilizationPermil,
            ],
            'reconciliation_queue' => [
                'pending_unmatched_count' => $pendingUnmatchedCount,
                'aged_over_7_days_count' => $agedOver7Days,
                'max_aging_days' => $maxAgingDays,
            ],
            'cash_pooling' => [
                'pools_count' => $poolsCount,
                'total_last_swept_idr' => $totalLastSweptIdr,
            ],
            'forecast_13w' => [
                'weeks_count' => $forecasts->count(),
                'min_closing_balance_idr' => $minClosing,
                'max_closing_balance_idr' => $maxClosing,
            ],
        ];
    }
}
