<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
| PROGRESS R0.3.b: SchemaIdentifierLengthTest (K-28).
| Memvalidasi bahwa seluruh identifier skema database (tabel, kolom, index, unique, foreign key)
| tidak melebihi batas 64 karakter (standar MySQL 8.4/PostgreSQL).
| Mencegah kegagalan portabilitas database di CI akibat identifier name too long.
*/

uses(TestCase::class, RefreshDatabase::class);

it('enforces all database schema identifiers do not exceed 64 characters', function (): void {
    $db = DB::connection();

    $violations = [];

    // 1. Periksa nama tabel
    $tables = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    foreach ($tables as $table) {
        $tableName = (string) $table->name;
        if (strlen($tableName) > 64) {
            $violations[] = [
                'type' => 'table',
                'name' => $tableName,
                'table' => $tableName,
                'length' => strlen($tableName),
            ];
        }

        // 2. Periksa nama kolom
        $columns = $db->select("PRAGMA table_info(\"{$tableName}\")");
        foreach ($columns as $column) {
            $columnName = (string) $column->name;
            if (strlen($columnName) > 64) {
                $violations[] = [
                    'type' => 'column',
                    'name' => $columnName,
                    'table' => $tableName,
                    'length' => strlen($columnName),
                ];
            }
        }

        // 3. Periksa index via PRAGMA index_list
        $indexes = $db->select("PRAGMA index_list(\"{$tableName}\")");
        foreach ($indexes as $index) {
            $indexName = (string) $index->name;
            if (str_starts_with($indexName, 'sqlite_autoindex_')) {
                continue;
            }
            if (strlen($indexName) > 64) {
                $violations[$indexName] = [
                    'type' => 'index',
                    'name' => $indexName,
                    'table' => $tableName,
                    'length' => strlen($indexName),
                ];
            }
        }
    }

    // 4. Periksa seluruh index eksplisit di sqlite_master
    $allIndexes = $db->select("SELECT name, tbl_name FROM sqlite_master WHERE type='index' AND name NOT LIKE 'sqlite_%'");
    foreach ($allIndexes as $idx) {
        $idxName = (string) $idx->name;
        if (strlen($idxName) > 64) {
            $violations[$idxName] = [
                'type' => 'index',
                'name' => $idxName,
                'table' => (string) $idx->tbl_name,
                'length' => strlen($idxName),
            ];
        }
    }

    $violationList = array_values($violations);

    if ($violationList !== []) {
        $details = implode("\n", array_map(
            fn ($v) => "- [{$v['type']}] {$v['name']} ({$v['length']} karakter, tabel: {$v['table']})",
            $violationList
        ));

        expect($violationList)->toBeEmpty(
            'Ditemukan '.count($violationList)." identifier melebihi batas 64 karakter:\n".$details
        );
    }

    expect($violationList)->toBeEmpty();
});
