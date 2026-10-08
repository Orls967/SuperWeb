<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TreasuryUnificationService (Fase 187)
 *
 * Implements:
 *  - 187.1 Unified payment hub across 30 lines with idempotent transaction key
 *  - 187.2 Daily intercompany netting clearing reducing gross flow
 *  - 187.4 Automated cash pool sweep with zero-negative balance invariant enforcement
 */
class TreasuryUnificationService
{
    /**
     * Process unified payment across 30 lines idempotently.
     */
    public function processPayment(string $txKey, string $lineCode, string $method, float $amountIdr): object
    {
        $existing = DB::table('pay_unified_transactions')->where('transaction_key', $txKey)->first();
        if ($existing) {
            return (object) $existing; // Idempotent return
        }

        $id = DB::table('pay_unified_transactions')->insertGetId([
            'transaction_key' => $txKey,
            'line_code' => strtoupper($lineCode),
            'payment_method' => strtoupper($method),
            'gross_amount_idr' => $amountIdr,
            'status' => 'SETTLED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pay_unified_transactions')->find($id);
    }

    /**
     * Clear intercompany receivables vs payables via multilateral netting.
     */
    public function executeIntercompanyNetting(string $entityA, string $entityB, float $receivablesA, float $payablesA): object
    {
        $net = round(abs($receivablesA - $payablesA), 2);
        $settling = ($receivablesA >= $payablesA) ? $entityB : $entityA;

        $batchCode = 'NET-PAY-'.strtoupper(Str::random(8));

        $id = DB::table('pay_intercompany_nettings')->insertGetId([
            'netting_batch_code' => $batchCode,
            'entity_a' => $entityA,
            'entity_b' => $entityB,
            'gross_receivables_idr' => $receivablesA,
            'gross_payables_idr' => $payablesA,
            'netted_settlement_idr' => $net,
            'settling_entity' => $settling,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pay_intercompany_nettings')->find($id);
    }

    /**
     * Perform cash pool sweep from operating entity to group master pool.
     * Enforces invariant: cash pool sweep cannot leave source account balance negative.
     */
    public function executePoolSweep(string $sourceAcc, string $targetPool, float $sweepAmount, float $sourceCurrentBalance): object
    {
        $balanceAfter = round($sourceCurrentBalance - $sweepAmount, 2);
        if ($balanceAfter < 0.0) {
            throw new \RuntimeException("Cash pool sweep rejected: Sweep amount ({$sweepAmount}) exceeds available balance ({$sourceCurrentBalance}). Invariant: Balance cannot be negative.");
        }

        $code = 'SWP-PAY-'.strtoupper(Str::random(8));

        $id = DB::table('pay_treasury_cash_pools')->insertGetId([
            'sweep_code' => $code,
            'source_account' => $sourceAcc,
            'target_pool_account' => $targetPool,
            'swept_amount_idr' => $sweepAmount,
            'source_balance_after_idr' => $balanceAfter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('pay_treasury_cash_pools')->find($id);
    }

    /**
     * Quality audit gate (`treasury:audit`).
     */
    public function audit(): array
    {
        $negativeBalances = DB::table('pay_treasury_cash_pools')
            ->where('source_balance_after_idr', '<', 0.0)
            ->count();

        return [
            'status' => $negativeBalances === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_transactions' => DB::table('pay_unified_transactions')->count(),
            'total_nettings' => DB::table('pay_intercompany_nettings')->count(),
            'total_sweeps' => DB::table('pay_treasury_cash_pools')->count(),
            'discrepancy_count' => $negativeBalances,
        ];
    }
}
