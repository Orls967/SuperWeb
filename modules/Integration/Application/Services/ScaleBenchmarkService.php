<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ScaleBenchmarkService (Fase 191)
 *
 * Implements:
 *  - 191.1 Checkpointed, idempotent seeder tracking across 30 lines with ledger Σ=0 balance verification
 *  - 191.2 Domain benchmark recording (throughput, execution duration, and peak memory)
 */
class ScaleBenchmarkService
{
    /**
     * Checkpoint or seed domain records idempotently.
     * Enforces ledger balance invariance: debit == credit.
     */
    public function recordSeederCheckpoint(string $domainCode, int $recordsCount, float $debitsIdr, float $creditsIdr): object
    {
        $imbalance = round($debitsIdr - $creditsIdr, 2);
        if ($imbalance !== 0.0) {
            throw new \RuntimeException("Seeder ledger imbalance in {$domainCode}: Debits ({$debitsIdr}) do not match Credits ({$creditsIdr}).");
        }

        DB::table('scl_seeder_checkpoints')->updateOrInsert(
            ['domain_code' => strtoupper($domainCode)],
            [
                'total_records_seeded' => $recordsCount,
                'total_ledger_debit_idr' => $debitsIdr,
                'total_ledger_credit_idr' => $creditsIdr,
                'status' => 'COMPLETED',
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('scl_seeder_checkpoints')->where('domain_code', strtoupper($domainCode))->first();
    }

    /**
     * Record domain benchmark performance metrics.
     */
    public function recordBenchmark(string $domainCode, string $operation, int $recordsProcessed, float $elapsedMs, float $peakMemoryMb): object
    {
        $key = 'BM-'.strtoupper($domainCode).'-'.strtoupper(Str::slug($operation)).'-'.strtoupper(Str::random(4));

        $id = DB::table('scl_benchmark_results')->insertGetId([
            'benchmark_key' => $key,
            'domain_code' => strtoupper($domainCode),
            'operation_name' => $operation,
            'records_processed' => $recordsProcessed,
            'elapsed_ms' => $elapsedMs,
            'peak_memory_mb' => $peakMemoryMb,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('scl_benchmark_results')->find($id);
    }

    /**
     * Quality audit gate (`scale:audit`).
     */
    public function audit(): array
    {
        $ledgerDiscrepancies = DB::table('scl_seeder_checkpoints')
            ->whereRaw('total_ledger_debit_idr != total_ledger_credit_idr')
            ->count();

        return [
            'status' => $ledgerDiscrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_checkpoints' => DB::table('scl_seeder_checkpoints')->count(),
            'total_benchmarks' => DB::table('scl_benchmark_results')->count(),
            'discrepancy_count' => $ledgerDiscrepancies,
        ];
    }
}
