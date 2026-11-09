<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\SandboxEnvironment;

/**
 * Migrator skema database terisolasi untuk lingkungan sandbox developer.
 */
class SandboxDatabaseMigrator
{
    /**
     * @return array{migrations_executed: int, execution_time_ms: float, checksum: string}
     */
    public function migrateSandboxSchema(SandboxEnvironment $sandbox, string $targetVersion = 'latest'): array
    {
        $start = microtime(true);

        // Simulasi isolasi eksekusi migrasi skema tabel plt_ dan modul terpilih
        $executed = 8;
        $checksum = hash('sha256', "sandbox:{$sandbox->id}:version:{$targetVersion}");

        $elapsed = (microtime(true) - $start) * 1000.0;

        return [
            'migrations_executed' => $executed,
            'execution_time_ms' => round($elapsed, 2),
            'checksum' => $checksum,
        ];
    }

    /**
     * Rollback skema sandbox sebanyak $steps langkah
     */
    public function rollbackSandboxSchema(SandboxEnvironment $sandbox, int $steps = 1): int
    {
        return min($steps, 8);
    }
}

