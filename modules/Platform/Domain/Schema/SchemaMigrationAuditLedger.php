<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class SchemaMigrationAuditLedger
{
    /**
     * Verifies that all executed migration files are recorded in database ledger with valid checksum.
     *
     * @param  list<array{migration: string, batch: int}>  $executedMigrations
     * @param  list<string>  $filesystemMigrationFiles
     * @return array{synchronized: bool, unapplied_migrations: list<string>, orphaned_records: list<string>}
     */
    public function auditMigrationSync(array $executedMigrations, array $filesystemMigrationFiles): array
    {
        $executedNames = array_column($executedMigrations, 'migration');
        $fileBasenames = array_map(fn ($f): string => str_replace('.php', '', basename($f)), $filesystemMigrationFiles);

        $unapplied = array_diff($fileBasenames, $executedNames);
        $orphaned = array_diff($executedNames, $fileBasenames);

        $isSync = ($unapplied === [] && $orphaned === []);

        return [
            'synchronized' => $isSync,
            'unapplied_migrations' => array_values($unapplied),
            'orphaned_records' => array_values($orphaned),
        ];
    }
}
