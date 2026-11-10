<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Closure;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Actions\Simulation\ProbeLedgerBalanceAction;
use RuntimeException;
use Throwable;

/**
 * Layanan orkestrasi isolasi sandbox transaksional tingkat tinggi (D35).
 * Membungkus eksekusi dalam DB savepoint / transaction yang di-rollback secara deterministik,
 * memverifikasi probe keseimbangan ledger, dan memastikan zero data contamination.
 */
final class SimulationSandboxIsolationService
{
    public function __construct(
        private readonly ProbeLedgerBalanceAction $ledgerProbe,
    ) {}

    /**
     * Mengeksekusi closure di dalam isolasi sandbox penuh dengan jaminan rollback.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return array{result: T, ledger_balanced: bool, rollback_success: bool, elapsed_ms: float}
     */
    public function executeInSandbox(Closure $callback): array
    {
        $start = microtime(true);
        DB::beginTransaction();

        $callbackResult = null;
        $ledgerBalanced = false;

        try {
            // Jalankan siklus bisnis simulasi
            $callbackResult = $callback();

            // Verifikasi invarian ledger probe sebelum rollback
            $probeResult = $this->ledgerProbe->execute();
            $ledgerBalanced = $probeResult['balanced'];

            if (! $ledgerBalanced) {
                throw new RuntimeException('Ledger probe tidak seimbang di akhir sandbox!');
            }
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            // Jaminan rollback penuh D35
            DB::rollBack();
        }

        $elapsed = round((microtime(true) - $start) * 1000, 2);

        return [
            'result' => $callbackResult,
            'ledger_balanced' => $ledgerBalanced,
            'rollback_success' => true,
            'elapsed_ms' => $elapsed,
        ];
    }

    /**
     * Memverifikasi bahwa tidak ada baris phantom yang bocor setelah transaksi sandbox di-rollback.
     *
     * @param  array<int, string>  $tables
     * @param  array<string, int>  $preExecutionCounts
     * @return array{leak_detected: bool, leaked_tables: array<string, int>}
     */
    public function verifyZeroLeakage(array $tables, array $preExecutionCounts): array
    {
        $leaked = [];
        $schema = DB::getSchemaBuilder();

        foreach ($tables as $tbl) {
            if ($schema->hasTable($tbl)) {
                $currentCount = DB::table($tbl)->count();
                $expectedCount = $preExecutionCounts[$tbl] ?? 0;

                if ($currentCount !== $expectedCount) {
                    $leaked[$tbl] = $currentCount - $expectedCount;
                }
            }
        }

        return [
            'leak_detected' => ! empty($leaked),
            'leaked_tables' => $leaked,
        ];
    }
}
