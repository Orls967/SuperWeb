<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;
use PhpToken;

/**
 * A12 — a `*_id` column created in a migration without a foreign-key constraint
 * (K-25). Intra-module references need FK + index; intentional cross-module
 * references are registered in config/modules.php `cross_module_columns`.
 * The check is per migration file: a FK added by a later migration is not seen.
 */
final class MissingForeignKeyRule implements Rule
{
    private const ID_COLUMN_METHODS = [
        'foreignid', 'foreignuuid', 'foreignulid', 'unsignedbiginteger', 'biginteger', 'unsignedinteger', 'integer',
        'unsignedmediuminteger', 'mediuminteger', 'unsignedsmallinteger', 'smallinteger', 'uuid', 'ulid', 'string', 'char',
    ];

    private const FOREIGN_KEY_CHAIN_METHODS = ['constrained', 'references', 'cascadeondelete', 'restrictondelete', 'nullondelete'];

    public function id(): string
    {
        return 'A12';
    }

    public function description(): string
    {
        return 'Kolom *_id di migrasi tanpa foreign key (kecuali terdaftar lintas modul)';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if ($file->module() === null || ! $file->isMigration()) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            $isSchemaCall = Tokens::isStaticCall($tokens, $index, ['Schema'], 'create')
                || Tokens::isStaticCall($tokens, $index, ['Schema'], 'table');
            $table = $isSchemaCall ? Tokens::stringLiteral($tokens[$index + 4] ?? $token) : null;

            if ($table === null) {
                continue;
            }

            $body = $this->closureBody($tokens, $index + 3);

            if ($body === null) {
                continue;
            }

            foreach ($this->columnsWithoutForeignKey($tokens, $body[0], $body[1]) as $column => $line) {
                if ($registry->isCrossModuleColumn($table, $column)) {
                    continue;
                }

                $violations[] = new Violation(
                    $this->id(),
                    $file->relativePath,
                    $line,
                    "{$table}.{$column}",
                    "Kolom {$table}.{$column} tanpa foreign key; tambah ->constrained() atau daftarkan sebagai kolom lintas modul.",
                );
            }
        }

        return $violations;
    }

    /**
     * Token range (exclusive of braces) of the closure passed to Schema::create/table.
     *
     * @param  list<PhpToken>  $tokens
     * @return array{int, int}|null
     */
    private function closureBody(array $tokens, int $callOpen): ?array
    {
        $callClose = Tokens::matchingClose($tokens, $callOpen);

        if ($callClose === null) {
            return null;
        }

        for ($cursor = $callOpen + 1; $cursor < $callClose; $cursor++) {
            if ($tokens[$cursor]->text === '{') {
                $close = Tokens::matchingClose($tokens, $cursor);

                return $close === null ? null : [$cursor + 1, $close];
            }
        }

        return null;
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @return array<string, int> column => line
     */
    private function columnsWithoutForeignKey(array $tokens, int $from, int $to): array
    {
        $idColumns = [];
        $foreignKeyColumns = [];
        $declaredColumns = [];
        $start = $from;

        while ($start < $to) {
            $end = min(Tokens::expressionEnd($tokens, $start, [';']), $to);
            $calls = $this->chainCalls($tokens, $start, $end);

            if ($calls !== []) {
                $first = $calls[0];
                $methods = array_column($calls, 'method');

                if (isset($first['strings'][0])) {
                    $declaredColumns[$first['strings'][0]] = true;
                }

                if (in_array($first['method'], self::ID_COLUMN_METHODS, true)) {
                    foreach ($first['strings'] as $column) {
                        if (str_ends_with($column, '_id')) {
                            $idColumns[$column] ??= $first['line'];

                            if (array_intersect($methods, self::FOREIGN_KEY_CHAIN_METHODS) !== []) {
                                $foreignKeyColumns[$column] = true;
                            }
                        }

                        break; // only the first argument is the column name
                    }
                }

                if ($first['method'] === 'foreign') {
                    foreach ($first['strings'] as $column) {
                        $foreignKeyColumns[$column] = true;
                    }
                }
            }

            $start = $end + 1;
        }

        foreach (array_keys($idColumns) as $column) {
            // `{name}_id` + `{name}_type` is a hand-written polymorphic reference; it cannot have a FK.
            if (isset($declaredColumns[substr($column, 0, -3).'_type'])) {
                $foreignKeyColumns[$column] = true;
            }
        }

        return array_diff_key($idColumns, $foreignKeyColumns);
    }

    /**
     * Method calls of a `$table->a(...)->b(...)` chain, with the string literals of each call's arguments.
     *
     * @param  list<PhpToken>  $tokens
     * @return list<array{method: string, strings: list<string>, line: int}>
     */
    private function chainCalls(array $tokens, int $from, int $to): array
    {
        if (! $tokens[$from]->is(T_VARIABLE)) {
            return [];
        }

        $calls = [];

        for ($cursor = $from + 1; $cursor < $to; $cursor++) {
            if (! $tokens[$cursor]->is(T_OBJECT_OPERATOR) || ! isset($tokens[$cursor + 2]) || $tokens[$cursor + 2]->text !== '(') {
                continue;
            }

            $open = $cursor + 2;
            $close = Tokens::matchingClose($tokens, $open) ?? $to;
            $strings = [];

            for ($argument = $open + 1; $argument < $close; $argument++) {
                $literal = Tokens::stringLiteral($tokens[$argument]);

                if ($literal !== null) {
                    $strings[] = $literal;
                }
            }

            $calls[] = ['method' => strtolower($tokens[$cursor + 1]->text), 'strings' => $strings, 'line' => $tokens[$cursor]->line];
            $cursor = $close;
        }

        return $calls;
    }
}
