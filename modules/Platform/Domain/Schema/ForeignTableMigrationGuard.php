<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class ForeignTableMigrationGuard
{
    /**
     * Verifies that a module's migrations only modify tables matching its assigned prefix.
     *
     * @param  string  $moduleName  e.g. 'Platform'
     * @param  string  $allowedPrefix  e.g. 'plt_'
     * @param  list<string>  $referencedTables  e.g. ['plt_sandboxes', 'plt_change_requests']
     * @return array{is_safe: bool, foreign_tables: list<string>}
     */
    public function validateTableOwnership(string $moduleName, string $allowedPrefix, array $referencedTables): array
    {
        $foreignTables = [];

        // Permitted shared system tables
        $systemTables = ['users', 'roles', 'permissions', 'migrations', 'failed_jobs'];

        foreach ($referencedTables as $table) {
            if (in_array($table, $systemTables, true)) {
                continue;
            }

            if (! str_starts_with($table, $allowedPrefix)) {
                $foreignTables[] = $table;
            }
        }

        $foreignTables = array_values(array_unique($foreignTables));

        return [
            'is_safe' => $foreignTables === [],
            'foreign_tables' => $foreignTables,
        ];
    }
}
