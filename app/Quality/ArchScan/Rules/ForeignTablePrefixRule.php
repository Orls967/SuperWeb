<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;

/**
 * A2 — a module creates, alters, queries or maps a table whose prefix belongs to
 * another module, or whose prefix is not registered at all (X2, X16, K-05).
 */
final class ForeignTablePrefixRule implements Rule
{
    public function id(): string
    {
        return 'A2';
    }

    public function description(): string
    {
        return 'Tabel berprefiks modul lain / tidak terdaftar (Schema::create|table, DB::table, $table model)';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        $module = $file->module();

        if ($module === null) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            $access = null;
            $table = null;

            foreach (['create', 'table'] as $method) {
                if (Tokens::isStaticCall($tokens, $index, ['Schema'], $method)) {
                    $access = "Schema::{$method}";
                    $table = Tokens::stringLiteral($tokens[$index + 4] ?? $token);
                }
            }

            if (Tokens::isStaticCall($tokens, $index, ['DB'], 'table')) {
                $access = 'DB::table';
                $table = Tokens::stringLiteral($tokens[$index + 4] ?? $token);
            }

            if ($token->is(T_VARIABLE) && $token->text === '$table'
                && ($tokens[$index + 1]->text ?? null) === '='
                && $this->isPropertyDeclaration($tokens, $index)) {
                $access = '$table';
                $table = Tokens::stringLiteral($tokens[$index + 2] ?? $token);
            }

            if ($access === null || $table === null) {
                continue;
            }

            $owner = $registry->ownerOfTable($table);

            if ($owner === $module) {
                continue;
            }

            $reason = $owner === null
                ? "prefiks tabel '{$table}' tidak terdaftar di config/modules.php"
                : "tabel '{$table}' milik modul {$owner}";

            $violations[] = new Violation(
                $this->id(),
                $file->relativePath,
                $token->line,
                "{$access}('{$table}')",
                "Modul {$module}: {$reason}.",
            );
        }

        return $violations;
    }

    /**
     * @param  list<\PhpToken>  $tokens
     */
    private function isPropertyDeclaration(array $tokens, int $index): bool
    {
        $previous = $tokens[$index - 1] ?? null;

        return $previous !== null
            && ($previous->is([T_PROTECTED, T_PUBLIC, T_PRIVATE, T_VAR]) || ($previous->is(T_STRING) && strtolower($previous->text) === 'string'));
    }
}
