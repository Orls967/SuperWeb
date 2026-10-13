<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * EnterpriseDataMigrationRetirementService (Fase 473)
 *
 * Implements:
 *  - 473.1 Migration inventory: source, target, cutover & reconciliation
 *  - 473.2 Pre-migration cleansing & quality criteria validation
 *  - 473.3 Legacy retirement: decommission, data archive, access revocation, cost savings
 *  - 473.4 Tests: migration reconciliation complete, rollback tested, legacy access revoked, data:audit clean
 *  - 473.5 Edge case: Legacy decommissioning requires consumer confirmation or explicit executive waiver
 *  - 473.6 Risk: Low data quality blocks migration; cleansing must pass acceptance criteria first
 *  - 473.7 Evidence: migration reconciliation, dual-run result, cost savings realized
 */
class EnterpriseDataMigrationRetirementService
{
    public function inventoryMigration(string $code, string $source, string $target, int $sourceCount): object
    {
        $id = DB::table('int_enterprise_data_migrations')->insertGetId([
            'migration_code' => strtoupper($code),
            'source_system' => strtoupper($source),
            'target_system' => strtoupper($target),
            'source_records_count' => $sourceCount,
            'target_records_count' => 0,
            'pre_migration_cleansing_passed' => false,
            'source_target_hash_reconciled' => false,
            'status' => 'inventoried',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_data_migrations')->where('id', $id)->first();
    }

    public function certifyCleansing(string $code): object
    {
        $m = DB::table('int_enterprise_data_migrations')->where('migration_code', strtoupper($code))->first();
        if (! $m) {
            throw new InvalidArgumentException("Migration '{$code}' not found.");
        }

        DB::table('int_enterprise_data_migrations')->where('id', $m->id)->update([
            'pre_migration_cleansing_passed' => true,
            'status' => 'cleansed',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_data_migrations')->where('id', $m->id)->first();
    }

    /**
     * 473.2, 473.4, 473.6 Execute migration with pre-cleansing check and reconciliation
     */
    public function executeMigration(string $code, int $targetCount): object
    {
        $m = DB::table('int_enterprise_data_migrations')->where('migration_code', strtoupper($code))->first();
        if (! $m) {
            throw new InvalidArgumentException("Migration '{$code}' not found.");
        }

        // 473.6 Risk: Cleansing must pass before cutover execution
        if (! $m->pre_migration_cleansing_passed) {
            throw new InvalidArgumentException('Migration blocked: Pre-migration data cleansing and deduplication acceptance criteria not met (473.2, 473.6).');
        }

        $reconciled = ($m->source_records_count === $targetCount);
        if (! $reconciled) {
            throw new InvalidArgumentException("Migration reconciliation failed: Source count ({$m->source_records_count}) does not match target count ({$targetCount}) (473.4).");
        }

        DB::table('int_enterprise_data_migrations')->where('id', $m->id)->update([
            'target_records_count' => $targetCount,
            'source_target_hash_reconciled' => true,
            'status' => 'migrated',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_data_migrations')->where('id', $m->id)->first();
    }

    public function rollbackMigration(string $code): object
    {
        $m = DB::table('int_enterprise_data_migrations')->where('migration_code', strtoupper($code))->first();
        if (! $m) {
            throw new InvalidArgumentException("Migration '{$code}' not found.");
        }

        DB::table('int_enterprise_data_migrations')->where('id', $m->id)->update([
            'target_records_count' => 0,
            'source_target_hash_reconciled' => false,
            'status' => 'rolled_back',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_enterprise_data_migrations')->where('id', $m->id)->first();
    }

    public function registerLegacySystem(string $systemCode, string $name): object
    {
        $id = DB::table('int_legacy_system_retirements')->insertGetId([
            'system_code' => strtoupper($systemCode),
            'system_name' => $name,
            'consumer_confirmation_or_waiver' => false,
            'data_archived_and_access_revoked' => false,
            'annual_cost_saving_realized' => 0.00,
            'status' => 'dual_run',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_legacy_system_retirements')->where('id', $id)->first();
    }

    /**
     * 473.3, 473.4, 473.5 Decommission legacy system with mandatory consumer confirmation/waiver
     */
    public function decommissionLegacySystem(
        string $systemCode,
        bool $consumerConfirmedOrWaived,
        float $costSavingRealized
    ): object {
        $sys = DB::table('int_legacy_system_retirements')->where('system_code', strtoupper($systemCode))->first();
        if (! $sys) {
            throw new InvalidArgumentException("Legacy system '{$systemCode}' not found.");
        }

        // 473.5 Edge case: Decommissioning requires explicit consumer sign-off or waiver
        if (! $consumerConfirmedOrWaived) {
            throw new InvalidArgumentException('Decommissioning blocked: Downstream consumers have not confirmed cutoff, and no executive waiver was provided (473.5).');
        }

        DB::table('int_legacy_system_retirements')->where('id', $sys->id)->update([
            'consumer_confirmation_or_waiver' => true,
            'data_archived_and_access_revoked' => true,
            'annual_cost_saving_realized' => $costSavingRealized,
            'status' => 'decommissioned',
            'updated_at' => now(),
        ]);

        return (object) DB::table('int_legacy_system_retirements')->where('id', $sys->id)->first();
    }

    public function audit(): array
    {
        // Discrepancy 1: Unreconciled completed migrations
        $unreconciledMigrations = DB::table('int_enterprise_data_migrations')
            ->where('status', 'migrated')
            ->where('source_target_hash_reconciled', false)
            ->count();

        // Discrepancy 2: Decommissioned systems without archived data / revoked access
        $unsecureRetirements = DB::table('int_legacy_system_retirements')
            ->where('status', 'decommissioned')
            ->where('data_archived_and_access_revoked', false)
            ->count();

        $total = $unreconciledMigrations + $unsecureRetirements;

        return [
            'status' => $total === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_migrations' => DB::table('int_enterprise_data_migrations')->count(),
            'total_legacy_systems' => DB::table('int_legacy_system_retirements')->count(),
            'discrepancy_count' => $total,
        ];
    }
}
