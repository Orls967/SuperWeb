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
 * A7 — a sensitive value (NIK, NPWP, passport number, secret, `*_encrypted`) is
 * "protected" with an encoding or an unkeyed hash (X10, K-22). base64 is not
 * encryption and sha256 of a 16-digit NIK is brute-forceable; use the
 * `encrypted` cast and a keyed blind index (hash_hmac).
 */
final class UnkeyedHashOfSensitiveValueRule implements Rule
{
    private const WEAK_FUNCTIONS = ['base64_encode', 'bin2hex', 'hash', 'md5', 'sha1', 'crc32', 'str_rot13'];

    public function id(): string
    {
        return 'A7';
    }

    public function description(): string
    {
        return 'base64/hash tanpa kunci untuk NIK/NPWP/paspor/secret/*_encrypted';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if ($file->module() === null) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            $target = $this->sensitiveTarget($tokens, $index);

            if ($target === null) {
                continue;
            }

            $start = $index + 2;
            $isAssignment = ($tokens[$index + 1]->text ?? '') === '=';
            $end = Tokens::expressionEnd($tokens, $start, $isAssignment ? [';'] : [',']);
            [$exprStart, $exprEnd] = $this->followVariable($tokens, $start, $end);
            $weak = $this->weakCalls($tokens, $exprStart, $exprEnd);

            if ($weak === []) {
                continue;
            }

            $violations[] = new Violation(
                $this->id(),
                $file->relativePath,
                $token->line,
                "{$target} = ".mb_strimwidth(Tokens::text($tokens, $start, $end - 1), 0, 160, '…'),
                "Nilai sensitif '{$target}' hanya di-".implode('/', $weak).'; pakai cast encrypted + blind index HMAC (KONSEP §A6).',
            );
        }

        return $violations;
    }

    public static function isSensitiveName(string $name): bool
    {
        $snake = strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $name));

        return preg_match('/(^|_)(nik|npwp)(_|$)|passport_?(number|no|num|id)|secret|(^|_)encrypted(_|$)/', $snake) === 1;
    }

    /**
     * Name of the sensitive column (array key or model property) being written at $index, if any.
     *
     * @param  list<PhpToken>  $tokens
     */
    private function sensitiveTarget(array $tokens, int $index): ?string
    {
        $token = $tokens[$index];
        $next = $tokens[$index + 1] ?? null;

        if ($next === null) {
            return null;
        }

        if ($token->is(T_CONSTANT_ENCAPSED_STRING) && $next->is(T_DOUBLE_ARROW)) {
            $key = (string) Tokens::stringLiteral($token);

            return self::isSensitiveName($key) ? $key : null;
        }

        if ($next->text === '=' && $token->is(T_STRING) && ($tokens[$index - 1] ?? null)?->is(T_OBJECT_OPERATOR)) {
            return self::isSensitiveName($token->text) ? $token->text : null;
        }

        return null;
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @return array{int, int}
     */
    private function followVariable(array $tokens, int $start, int $end): array
    {
        if ($end - $start !== 1 || ! $tokens[$start]->is(T_VARIABLE)) {
            return [$start, $end];
        }

        for ($cursor = $start - 1; $cursor >= 0; $cursor--) {
            if ($tokens[$cursor]->is([T_FUNCTION, T_FN])) {
                break;
            }

            if ($tokens[$cursor]->is(T_VARIABLE) && $tokens[$cursor]->text === $tokens[$start]->text && ($tokens[$cursor + 1]->text ?? '') === '=') {
                return [$cursor + 2, Tokens::expressionEnd($tokens, $cursor + 2, [';'])];
            }
        }

        return [$start, $end];
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @return list<string>
     */
    private function weakCalls(array $tokens, int $from, int $to): array
    {
        $calls = [];

        for ($cursor = $from; $cursor < $to; $cursor++) {
            foreach (self::WEAK_FUNCTIONS as $function) {
                if (Tokens::isFunctionCall($tokens, $cursor, $function)) {
                    $calls[] = $function;
                }
            }
        }

        return array_values(array_unique($calls));
    }
}
