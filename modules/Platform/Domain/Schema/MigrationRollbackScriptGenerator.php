<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class MigrationRollbackScriptGenerator
{
    /**
     * Synthesizes automated inverse DDL statements for emergency rollback.
     *
     * @param  list<array{action: string, table: string, column?: string, type?: string, index?: string}>  $appliedOperations
     * @return list<string> inverse SQL statements
     */
    public function generateInverseStatements(array $appliedOperations): array
    {
        $inverseStatements = [];

        // Reverse order of applied operations
        $reversed = array_reverse($appliedOperations);

        foreach ($reversed as $op) {
            $table = $op['table'] ?? 'unknown_table';
            $action = $op['action'] ?? '';

            switch ($action) {
                case 'add_column':
                    $col = $op['column'] ?? 'unknown_column';
                    $inverseStatements[] = "ALTER TABLE `{$table}` DROP COLUMN `{$col}`, ALGORITHM=INSTANT;";
                    break;

                case 'drop_column':
                    $col = $op['column'] ?? 'unknown_column';
                    $type = $op['type'] ?? 'VARCHAR(255)';
                    $inverseStatements[] = "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$type} NULL, ALGORITHM=INSTANT; -- Perhatian: data historis perlu direstore dari snapshot!";
                    break;

                case 'add_index':
                    $idx = $op['index'] ?? 'idx_unknown';
                    $inverseStatements[] = "ALTER TABLE `{$table}` DROP INDEX `{$idx}`, ALGORITHM=INPLACE;";
                    break;

                case 'create_table':
                    $inverseStatements[] = "DROP TABLE IF EXISTS `{$table}`;";
                    break;

                default:
                    $inverseStatements[] = "-- Operasi '{$action}' tidak memiliki inverse deterministik otomatis.";
            }
        }

        return $inverseStatements;
    }
}
