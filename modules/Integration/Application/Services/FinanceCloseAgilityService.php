<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FinanceCloseAgilityService (Fase 209)
 *
 * Implements:
 *  - 209.1 Continuous close subledger reconciliation with strict zero-variance period locking
 *  - 209.3 Intercompany automatic invoice vs bill matching & elimination
 */
class FinanceCloseAgilityService
{
    /**
     * Reconcile subledger and lock period if zero variance.
     */
    public function reconcileAndLockPeriod(string $period, float $glBalance, float $subledgerBalance, string $officer): object
    {
        $variance = round(abs($glBalance - $subledgerBalance), 2);
        if ($variance > 0.0) {
            throw new \RuntimeException("Close rejected: Variance IDR {$variance} detected between GL and subledger. Cannot lock period.");
        }

        DB::table('fin_close_period_locks')->updateOrInsert(
            ['period_code' => strtoupper($period)],
            [
                'general_ledger_balance_idr' => $glBalance,
                'subledger_aggregate_idr' => $subledgerBalance,
                'reconciliation_variance_idr' => 0.00,
                'is_period_locked' => true,
                'locked_by' => $officer,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('fin_close_period_locks')->where('period_code', strtoupper($period))->first();
    }

    /**
     * Match intercompany invoice vs bill for clean group consolidation.
     */
    public function matchIntercompanyTransactions(string $fromEntity, string $toEntity, float $invAmount, float $billAmount): object
    {
        $code = 'IC-'.strtoupper(Str::random(8));
        $diff = round(abs($invAmount - $billAmount), 2);
        $status = $diff === 0.0 ? 'BALANCED' : 'MISMATCHED';

        $id = DB::table('fin_intercompany_matchings')->insertGetId([
            'match_code' => $code,
            'source_entity_code' => strtoupper($fromEntity),
            'target_entity_code' => strtoupper($toEntity),
            'sender_invoice_amount_idr' => $invAmount,
            'receiver_bill_amount_idr' => $billAmount,
            'variance_idr' => $diff,
            'elimination_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('fin_intercompany_matchings')->find($id);
    }

    /**
     * Quality audit gate (`enterprise:audit`).
     */
    public function audit(): array
    {
        $unbalancedLocks = DB::table('fin_close_period_locks')
            ->where('is_period_locked', true)
            ->where('reconciliation_variance_idr', '!=', 0.0)
            ->count();

        $mismatchedIc = DB::table('fin_intercompany_matchings')
            ->where('elimination_status', 'MISMATCHED')
            ->count();

        $discrepancies = $unbalancedLocks + $mismatchedIc;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_period_locks' => DB::table('fin_close_period_locks')->count(),
            'total_ic_matches' => DB::table('fin_intercompany_matchings')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
