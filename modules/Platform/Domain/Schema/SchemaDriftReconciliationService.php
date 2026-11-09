<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Schema;

final class SchemaDriftReconciliationService
{
    /**
     * Compares active database schema against migration expectation to identify schema drift.
     *
     * @param  array<string, list<string>>  $expectedSchema  Table => list of columns
     * @param  array<string, list<string>>  $actualSchema  Table => list of columns
     * @return array{has_drift: bool, missing_tables: list<string>, extra_tables: list<string>, missing_columns: array<string, list<string>>, extra_columns: array<string, list<string>>}
     */
    public function detectDrift(array $expectedSchema, array $actualSchema): array
    {
        $missingTables = array_diff(array_keys($expectedSchema), array_keys($actualSchema));
        $extraTables = array_diff(array_keys($actualSchema), array_keys($expectedSchema));

        $missingColumns = [];
        $extraColumns = [];

        foreach ($expectedSchema as $table => $expectedCols) {
            if (! isset($actualSchema[$table])) {
                continue;
            }

            $actualCols = $actualSchema[$table];
            $diffMissing = array_diff($expectedCols, $actualCols);
            $diffExtra = array_diff($actualCols, $expectedCols);

            if ($diffMissing !== []) {
                $missingColumns[$table] = array_values($diffMissing);
            }
            if ($diffExtra !== []) {
                $extraColumns[$table] = array_values($diffExtra);
            }
        }

        $hasDrift = ($missingTables !== [] || $extraTables !== [] || $missingColumns !== [] || $extraColumns !== []);

        return [
            'has_drift' => $hasDrift,
            'missing_tables' => array_values($missingTables),
            'extra_tables' => array_values($extraTables),
            'missing_columns' => $missingColumns,
            'extra_columns' => $extraColumns,
        ];
    }
}
