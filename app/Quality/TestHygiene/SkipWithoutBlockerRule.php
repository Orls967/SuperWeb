<?php

declare(strict_types=1);

namespace App\Quality\TestHygiene;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;
use PhpToken;

/**
 * T3 — a skipped, incomplete or todo test without a reference to docs/BLOCKERS.md.
 * Skipping silently hides missing behaviour; a skip must point at the blocker.
 */
final class SkipWithoutBlockerRule implements Rule
{
    private const SKIP_CALLS = ['marktestskipped', 'marktestincomplete', 'skip', 'todo'];

    public function id(): string
    {
        return 'T3';
    }

    public function description(): string
    {
        return 'Test di-skip/incomplete/todo tanpa rujukan BLOCKERS';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if (! $file->isTest()) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            $method = strtolower($token->text);
            $previous = $tokens[$index - 1] ?? null;
            $isCall = $token->is(T_STRING) && in_array($method, self::SKIP_CALLS, true)
                && ($tokens[$index + 1]->text ?? '') === '('
                && $previous !== null && $previous->is([T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR]);

            if (! $isCall) {
                continue;
            }

            $close = Tokens::matchingClose($tokens, $index + 1) ?? $index + 1;
            $first = $tokens[$index + 2] ?? null;

            if ($method === 'skip' && ! self::looksLikePestSkip($first)) {
                continue; // Collection::skip(3) is an offset, not a skipped test
            }

            $arguments = Tokens::text($tokens, $index + 2, $close - 1);

            if (str_contains(strtoupper($arguments), 'BLOCKERS')) {
                continue;
            }

            $violations[] = new Violation($this->id(), $file->relativePath, $token->line, $token->text.'()', 'Test dilewati tanpa rujukan docs/BLOCKERS.md; tulis alasan + ID blocker di argumen.');
        }

        return $violations;
    }

    /**
     * Pest's skip() takes nothing, a message, a boolean or a closure; Collection::skip() takes a count.
     */
    private static function looksLikePestSkip(?PhpToken $first): bool
    {
        if ($first === null || $first->text === ')' || $first->text === '"') {
            return true;
        }

        if ($first->is([T_CONSTANT_ENCAPSED_STRING, T_START_HEREDOC, T_FN, T_FUNCTION])) {
            return true;
        }

        return $first->is(T_STRING) && in_array(strtolower($first->text), ['true', 'false'], true);
    }
}
