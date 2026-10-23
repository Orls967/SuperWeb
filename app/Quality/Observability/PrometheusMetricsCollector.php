<?php

declare(strict_types=1);

namespace App\Quality\Observability;

use App\Quality\Audit\AuditCommandRegistry;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\OpenApi\OpenApiSpecGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise Prometheus Metrics Collector for SuperWeb 46 Modules Platform (Tahap 12).
 *
 * Emits genuine operational metrics adhering to Prometheus Exposition Format (text/plain; version=0.0.4).
 */
final class PrometheusMetricsCollector
{
    /**
     * Canonical 46 enterprise modules in the AutoServe ecosystem.
     *
     * @var list<string>
     */
    public const CANONICAL_46_MODULES = OpenApiSpecGenerator::CANONICAL_46_MODULES;

    /**
     * Architectural ratchet rules A1-A13 (KONSEP.md §A14.1).
     *
     * @var list<string>
     */
    public const ARCH_RULES = [
        'A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9', 'A10', 'A11', 'A12', 'A13',
    ];

    /**
     * Collects all real-time metrics across 46 modules and formats as Prometheus exposition text.
     */
    public function render(): string
    {
        $metrics = $this->collect();

        $lines = [];

        // 1. Registered Modules
        $lines[] = '# HELP superweb_modules_registered_total Total number of registered canonical modules in SuperWeb platform.';
        $lines[] = '# TYPE superweb_modules_registered_total gauge';
        $lines[] = sprintf('superweb_modules_registered_total %d', $metrics['modules_registered_total']);
        $lines[] = '';

        // 2. Architecture Violations per Rule (A1-A13)
        $lines[] = '# HELP superweb_arch_scan_violations_total Total architecture violations detected per rule (A1-A13).';
        $lines[] = '# TYPE superweb_arch_scan_violations_total gauge';
        foreach ($metrics['arch_violations'] as $rule => $count) {
            $lines[] = sprintf('superweb_arch_scan_violations_total{rule="%s"} %d', $rule, $count);
        }
        $lines[] = '';

        // 3. Registered Audit Commands
        $lines[] = '# HELP superweb_audit_commands_registered_total Total registered audit, reconciliation, and verification commands.';
        $lines[] = '# TYPE superweb_audit_commands_registered_total gauge';
        $lines[] = sprintf('superweb_audit_commands_registered_total %d', $metrics['audit_commands_registered_total']);
        $lines[] = '';

        // 4. Registered Routes
        $lines[] = '# HELP superweb_routes_registered_total Total registered routes across all modules.';
        $lines[] = '# TYPE superweb_routes_registered_total gauge';
        $lines[] = sprintf('superweb_routes_registered_total %d', $metrics['routes_registered_total']);
        $lines[] = '';

        // 5. Ledger Accounts & Entries
        $lines[] = '# HELP superweb_ledger_accounts_total Total ledger accounts registered in banking double-entry bookkeeping.';
        $lines[] = '# TYPE superweb_ledger_accounts_total gauge';
        $lines[] = sprintf('superweb_ledger_accounts_total %d', $metrics['ledger_accounts_total']);
        $lines[] = '';

        $lines[] = '# HELP superweb_ledger_entries_total Total double-entry journal entries recorded.';
        $lines[] = '# TYPE superweb_ledger_entries_total counter';
        $lines[] = sprintf('superweb_ledger_entries_total %d', $metrics['ledger_entries_total']);
        $lines[] = '';

        // 6. Database Tables Count per Module (46 Modules)
        $lines[] = '# HELP superweb_module_tables_count Total database tables owned by module across 46 canonical modules.';
        $lines[] = '# TYPE superweb_module_tables_count gauge';
        foreach ($metrics['module_tables_count'] as $module => $count) {
            $lines[] = sprintf('superweb_module_tables_count{module="%s"} %d', $module, $count);
        }
        $lines[] = '';

        // 7. System Runtime Info
        $lines[] = '# HELP superweb_runtime_info Runtime environment details and versioning.';
        $lines[] = '# TYPE superweb_runtime_info gauge';
        $lines[] = sprintf(
            'superweb_runtime_info{php_version="%s",environment="%s"} 1',
            PHP_VERSION,
            app()->environment()
        );
        $lines[] = '';

        $lines[] = '# HELP superweb_memory_usage_bytes Current process memory footprint in bytes.';
        $lines[] = '# TYPE superweb_memory_usage_bytes gauge';
        $lines[] = sprintf('superweb_memory_usage_bytes %d', memory_get_usage(true));
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Gathers raw metrics data array.
     *
     * @return array{
     *     modules_registered_total: int,
     *     arch_violations: array<string, int>,
     *     audit_commands_registered_total: int,
     *     routes_registered_total: int,
     *     ledger_accounts_total: int,
     *     ledger_entries_total: int,
     *     module_tables_count: array<string, int>
     * }
     */
    public function collect(): array
    {
        return [
            'modules_registered_total' => count(self::CANONICAL_46_MODULES),
            'arch_violations' => $this->collectArchViolations(),
            'audit_commands_registered_total' => $this->collectAuditCommandsCount(),
            'routes_registered_total' => $this->collectRoutesCount(),
            'ledger_accounts_total' => $this->collectLedgerAccountsCount(),
            'ledger_entries_total' => $this->collectLedgerEntriesCount(),
            'module_tables_count' => $this->collectModuleTablesCount(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function collectArchViolations(): array
    {
        $violations = array_fill_keys(self::ARCH_RULES, 0);

        // Check if arch-scan.json baseline or artifact log exists
        $logPath = base_path('storage/logs/arch-scan.json');
        $baselinePath = base_path('tests/Architecture/baselines/arch-scan.json');

        $sourcePath = file_exists($logPath) ? $logPath : (file_exists($baselinePath) ? $baselinePath : null);

        if ($sourcePath !== null) {
            try {
                $decoded = json_decode((string) file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
                $rulesData = $decoded['rules'] ?? [];
                foreach (self::ARCH_RULES as $rule) {
                    if (isset($rulesData[$rule]['total']) && is_numeric($rulesData[$rule]['total'])) {
                        $violations[$rule] = (int) $rulesData[$rule]['total'];
                    }
                }
            } catch (\Throwable) {
                // Keep default zeros
            }
        }

        return $violations;
    }

    private function collectAuditCommandsCount(): int
    {
        try {
            return count(AuditCommandRegistry::auditCommands());
        } catch (\Throwable) {
            return 0;
        }
    }

    private function collectRoutesCount(): int
    {
        try {
            return count(Route::getRoutes()->getRoutes());
        } catch (\Throwable) {
            return 0;
        }
    }

    private function collectLedgerAccountsCount(): int
    {
        try {
            if (Schema::hasTable('bank_ledger_accounts')) {
                return (int) DB::table('bank_ledger_accounts')->count();
            }
        } catch (\Throwable) {
            // Fallback to 0 if database uninitialized
        }

        return 0;
    }

    private function collectLedgerEntriesCount(): int
    {
        try {
            if (Schema::hasTable('bank_ledger_entries')) {
                return (int) DB::table('bank_ledger_entries')->count();
            }
        } catch (\Throwable) {
            // Fallback to 0 if database uninitialized
        }

        return 0;
    }

    /**
     * @return array<string, int>
     */
    private function collectModuleTablesCount(): array
    {
        $counts = array_fill_keys(self::CANONICAL_46_MODULES, 0);

        try {
            $registry = ModuleRegistry::fromConfig((array) config('modules', []));
            $tables = Schema::getTableListing();

            foreach ($tables as $rawTable) {
                // Strip SQLite / Postgres schema prefix if present (e.g. main.table_name)
                $table = (string) preg_replace('/^[^.]+\./', '', (string) $rawTable);
                $owner = $registry->ownerOfTable($table);
                if ($owner !== null && isset($counts[$owner])) {
                    $counts[$owner]++;
                }
            }
        } catch (\Throwable) {
            // Fallback if DB connection fails
        }

        // If table count in DB is zero for any module, fallback to counting migration files
        foreach (self::CANONICAL_46_MODULES as $mod) {
            if ($counts[$mod] === 0) {
                $migDir = base_path("modules/{$mod}/database/migrations");
                if (is_dir($migDir)) {
                    $files = scandir($migDir) ?: [];
                    $phpFiles = array_filter($files, static fn (string $f): bool => str_ends_with($f, '.php'));
                    if (count($phpFiles) > 0) {
                        $counts[$mod] = count($phpFiles);
                    }
                }
            }
        }

        return $counts;
    }
}
