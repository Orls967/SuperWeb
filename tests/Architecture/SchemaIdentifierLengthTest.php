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

/**
 * KETERBATASAN ARSITEKTUR (D1, K-28, PROGRESS Register Minus R0):
 * Pemeriksaan panjang key index komposit di bawah ini menggunakan estimasi statis regex berbasis deklarasi skema
 * migrasi (Schema::create & Schema::table), bukan koneksi live MySQL runtime DDL.
 * Asumsi yang digunakan:
 * - Charset utf8mb4: 1 karakter string/char memakan 4 byte pada index InnoDB.
 * - String tanpa argumen panjang default ke 255 karakter (1.020 byte).
 * - Batas InnoDB prefix key length adalah 3.072 byte (MySQL 8.0+).
 */
it('enforces all database index composite key lengths do not exceed 3072 bytes for MySQL utf8mb4 portability', function (): void {
    $migrations = array_merge(
        glob(base_path('modules/*/database/migrations/*.php')),
        glob(database_path('migrations/*.php'))
    );
    sort($migrations);

    // Pass 1: Kumpulkan seluruh definisi tipe/panjang kolom per tabel dari create & table
    $tableColumns = [];

    foreach ($migrations as $file) {
        $code = (string) file_get_contents($file);
        if (preg_match_all("/Schema::(?:create|table)\(\s*[\x27\"]([^\x27\"]+)[\x27\"]\s*,\s*function\s*\([^)]*\)\s*\{(.*?)\}\s*\);/s", $code, $tableMatches, PREG_SET_ORDER)) {
            foreach ($tableMatches as $tm) {
                $tableName = $tm[1];
                $tableBody = $tm[2];

                if (preg_match_all("/\\\$table->([a-zA-Z0-9_]+)\(\s*[\x27\"]([^\x27\"]+)[\x27\"](?:\s*,\s*([0-9]+))?/", $tableBody, $colMatches, PREG_SET_ORDER)) {
                    foreach ($colMatches as $cm) {
                        $type = $cm[1];
                        $name = $cm[2];
                        $len = isset($cm[3]) && is_numeric($cm[3]) ? (int) $cm[3] : null;

                        if ($type === 'string') {
                            $bytes = ($len ?? 255) * 4;
                        } elseif ($type === 'char') {
                            $bytes = ($len ?? 36) * 4;
                        } elseif ($type === 'uuid') {
                            $bytes = 36 * 4;
                        } elseif ($type === 'text' || $type === 'longText') {
                            $bytes = 3072;
                        } elseif (in_array($type, ['integer', 'unsignedInteger'], true)) {
                            $bytes = 4;
                        } elseif (in_array($type, ['bigInteger', 'unsignedBigInteger', 'foreignId', 'id'], true)) {
                            $bytes = 8;
                        } elseif (in_array($type, ['tinyInteger', 'boolean'], true)) {
                            $bytes = 1;
                        } elseif (in_array($type, ['date', 'timestamp', 'dateTime'], true)) {
                            $bytes = 8;
                        } else {
                            $bytes = ($len ?? 255) * 4;
                        }

                        $tableColumns[$tableName][$name] = $bytes;
                    }
                }
            }
        }
    }

    // Pass 2: Evaluasi seluruh unique dan index komposit
    $violations = [];

    foreach ($migrations as $file) {
        $code = (string) file_get_contents($file);
        if (preg_match_all("/Schema::(?:create|table)\(\s*[\x27\"]([^\x27\"]+)[\x27\"]\s*,\s*function\s*\([^)]*\)\s*\{(.*?)\}\s*\);/s", $code, $tableMatches, PREG_SET_ORDER)) {
            foreach ($tableMatches as $tm) {
                $tableName = $tm[1];
                $tableBody = $tm[2];

                if (preg_match_all("/\\\$table->(unique|index)\(\s*\[([^\]]+)\]/", $tableBody, $idxMatches, PREG_SET_ORDER)) {
                    foreach ($idxMatches as $im) {
                        $type = $im[1];
                        $cols = array_map(fn ($c) => trim($c, " \t\n\r\0\x0B\x27\""), explode(',', $im[2]));
                        $total = 0;
                        foreach ($cols as $c) {
                            $total += $tableColumns[$tableName][$c] ?? (255 * 4);
                        }
                        if ($total > 3072) {
                            $violations[] = [
                                'file' => basename($file),
                                'table' => $tableName,
                                'type' => $type,
                                'cols' => implode(', ', $cols),
                                'bytes' => $total,
                            ];
                        }
                    }
                }
            }
        }
    }

    if ($violations !== []) {
        $details = implode("\n", array_map(
            fn ($v) => "- [{$v['file']}] {$v['table']} ({$v['type']}: [{$v['cols']}] = {$v['bytes']} bytes)",
            $violations
        ));

        expect($violations)->toBeEmpty(
            'Ditemukan index/unique melebihi batas 3072 byte (MySQL utf8mb4):'."\n".$details
        );
    }

    expect($violations)->toBeEmpty();
});

test('semua pemanggilan comment pada kolom migrasi bertipe string bukan array', function () {
    $migrations = glob(database_path('migrations/*.php'));
    foreach (glob(base_path('modules/*/database/migrations/*.php')) as $modMig) {
        $migrations[] = $modMig;
    }

    $invalidComments = [];
    foreach ($migrations as $file) {
        $code = (string) file_get_contents($file);
        if (preg_match_all("/->comment\(\s*\[/s", $code, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $m) {
                $line = substr_count(substr($code, 0, $m[1]), "\n") + 1;
                $invalidComments[] = basename($file).":{$line}";
            }
        }
    }

    expect($invalidComments)->toBeEmpty(
        'Ditemukan pemanggilan ->comment([...]) dengan array yang menyebabkan TypeError addslashes di MySQL Grammars:'."\n"
        .implode("\n", $invalidComments)
    );
});

test('evaluasi estimasi panjang key komposit menangkap Schema::table, string panjang eksplisit, char, dan uuid', function () {
    $evaluator = function (string $migrationCode): array {
        $tableColumns = [];
        if (preg_match_all("/Schema::(?:create|table)\(\s*[\x27\"]([^\x27\"]+)[\x27\"]\s*,\s*function\s*\([^)]*\)\s*\{(.*?)\}\s*\);/s", $migrationCode, $tableMatches, PREG_SET_ORDER)) {
            foreach ($tableMatches as $tm) {
                $tableName = $tm[1];
                $tableBody = $tm[2];

                if (preg_match_all("/\\\$table->([a-zA-Z0-9_]+)\(\s*[\x27\"]([^\x27\"]+)[\x27\"](?:\s*,\s*([0-9]+))?/", $tableBody, $colMatches, PREG_SET_ORDER)) {
                    foreach ($colMatches as $cm) {
                        $type = $cm[1];
                        $name = $cm[2];
                        $len = isset($cm[3]) && is_numeric($cm[3]) ? (int) $cm[3] : null;

                        if ($type === 'string') {
                            $bytes = ($len ?? 255) * 4;
                        } elseif ($type === 'char') {
                            $bytes = ($len ?? 36) * 4;
                        } elseif ($type === 'uuid') {
                            $bytes = 36 * 4;
                        } elseif ($type === 'text' || $type === 'longText') {
                            $bytes = 3072;
                        } elseif (in_array($type, ['integer', 'unsignedInteger'], true)) {
                            $bytes = 4;
                        } elseif (in_array($type, ['bigInteger', 'unsignedBigInteger', 'foreignId', 'id'], true)) {
                            $bytes = 8;
                        } elseif (in_array($type, ['tinyInteger', 'boolean'], true)) {
                            $bytes = 1;
                        } elseif (in_array($type, ['date', 'timestamp', 'dateTime'], true)) {
                            $bytes = 8;
                        } else {
                            $bytes = ($len ?? 255) * 4;
                        }

                        $tableColumns[$tableName][$name] = $bytes;
                    }
                }
            }
        }

        $violations = [];
        if (preg_match_all("/Schema::(?:create|table)\(\s*[\x27\"]([^\x27\"]+)[\x27\"]\s*,\s*function\s*\([^)]*\)\s*\{(.*?)\}\s*\);/s", $migrationCode, $tableMatches, PREG_SET_ORDER)) {
            foreach ($tableMatches as $tm) {
                $tableName = $tm[1];
                $tableBody = $tm[2];

                if (preg_match_all("/\\\$table->(unique|index)\(\s*\[([^\]]+)\]/", $tableBody, $idxMatches, PREG_SET_ORDER)) {
                    foreach ($idxMatches as $im) {
                        $cols = array_map(fn ($c) => trim($c, " \t\n\r\0\x0B\x27\""), explode(',', $im[2]));
                        $total = 0;
                        foreach ($cols as $c) {
                            $total += $tableColumns[$tableName][$c] ?? (255 * 4);
                        }
                        if ($total > 3072) {
                            $violations[] = ['table' => $tableName, 'bytes' => $total];
                        }
                    }
                }
            }
        }

        return $violations;
    };

    // Fixture 1: Schema::table adding index on composite exceeding 3072 bytes (4 default strings = 4080 bytes)
    $violatingCode = <<<'PHP'
Schema::create('sample_tbl', function ($table) {
    $table->string('col_a');
    $table->string('col_b');
    $table->string('col_c');
    $table->string('col_d');
});
Schema::table('sample_tbl', function ($table) {
    $table->index(['col_a', 'col_b', 'col_c', 'col_d']);
});
PHP;
    $result1 = $evaluator($violatingCode);
    expect($result1)->toHaveCount(1)
        ->and($result1[0]['bytes'])->toBe(4080);

    // Fixture 2: Schema::table with uuid, char(10), and string(50) = 36*4 + 10*4 + 50*4 = 384 bytes <= 3072
    $cleanCode = <<<'PHP'
Schema::create('sample_tbl_2', function ($table) {
    $table->uuid('uuid_col');
    $table->char('char_col', 10);
    $table->string('str_col', 50);
});
Schema::table('sample_tbl_2', function ($table) {
    $table->index(['uuid_col', 'char_col', 'str_col']);
});
PHP;
    $result2 = $evaluator($cleanCode);
    expect($result2)->toBeEmpty();
});
