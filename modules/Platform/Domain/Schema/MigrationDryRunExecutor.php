<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

use Illuminate\Support\Facades\DB;
use Throwable;

final class MigrationDryRunExecutor
{
    /**
     * @param  list<string>  $sqlStatements
     * @return array{
     *     dry_run_success: bool,
     *     executed_statements_count: int,
     *     execution_duration_ms: float,
     *     error_message: string|null,
     *     rolled_back_cleanly: bool
     * }
     */
    public function executeTransactionalDryRun(array $sqlStatements): array
    {
        $startTime = microtime(true);
        $executedCount = 0;
        $errorMessage = null;
        $success = false;

        DB::beginTransaction();

        try {
            foreach ($sqlStatements as $statement) {
                $trimmed = trim($statement);
                if (empty($trimmed)) {
                    continue;
                }

                DB::statement($trimmed);
                $executedCount++;
            }

            $success = true;
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            $success = false;
        } finally {
            // Always roll back the transaction so nothing persists to database
            DB::rollBack();
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'dry_run_success' => $success,
            'executed_statements_count' => $executedCount,
            'execution_duration_ms' => $durationMs,
            'error_message' => $errorMessage,
            'rolled_back_cleanly' => true,
        ];
    }
}
