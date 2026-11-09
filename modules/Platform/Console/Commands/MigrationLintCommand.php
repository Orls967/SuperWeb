<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Algorithms\MigrationSafetyLinter;

class MigrationLintCommand extends Command
{
    protected $signature = 'platform:migration-lint
                            {--path=modules/Platform/database/migrations : Direktori migrasi yang dipindai}';

    protected $description = 'Memindai integritas keamanan DDL migrasi database MySQL 8.4';

    public function handle(MigrationSafetyLinter $linter): int
    {
        $targetPath = base_path($this->option('path'));
        $this->info("Memindai file migrasi di: {$targetPath}");

        if (! is_dir($targetPath)) {
            $this->warn("Direktori tidak ditemukan: {$targetPath}");

            return 0;
        }

        $files = glob("{$targetPath}/*.php") ?: [];
        $totalViolations = 0;

        foreach ($files as $file) {
            $violations = $linter->lintFile($file);
            if (! empty($violations)) {
                $this->error("Pelanggaran pada {$file}:");
                foreach ($violations as $v) {
                    $this->line("  [{$v->rule}] Baris {$v->line}: {$v->message}");
                    $totalViolations++;
                }
            }
        }

        if ($totalViolations > 0) {
            $this->error("Ditemukan {$totalViolations} pelanggaran DDL kritis! Deployment dihentikan.");

            return 1;
        }

        $this->info('Semua file migrasi aman dan kompatibel dengan standar zero-downtime MySQL 8.4.');

        return 0;
    }
}
