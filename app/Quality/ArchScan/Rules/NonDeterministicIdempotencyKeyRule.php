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
 * A4 — an idempotency key built from random or time-based values (X4, K-12).
 * A retry would then produce a new key and post twice; keys must come from the
 * business identity (`{prefix}:{action}:{id}`).
 */
final class NonDeterministicIdempotencyKeyRule implements Rule
{
    private const RANDOM_STATIC_CALLS = [
        'str' => ['uuid', 'ordereduuid', 'ulid', 'random'],
        'uuid' => ['uuid1', 'uuid4', 'uuid6', 'uuid7'],
        'carbon' => ['now', 'today'],
        'carbonimmutable' => ['now', 'today'],
        'date' => ['now', 'today'],
    ];

    private const RANDOM_FUNCTIONS = ['uniqid', 'random_bytes', 'random_int', 'rand', 'mt_rand', 'now', 'today', 'time', 'microtime', 'hrtime', 'lcg_value'];

    public function id(): string
    {
        return 'A4';
    }

    public function description(): string
    {
        return 'Idempotency key dari nilai acak/waktu (Str::uuid, Str::random, uniqid, now(), time(), …)';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        if ($file->module() === null) {
            return [];
        }

        $tokens = $file->tokens();
        $violations = [];

        foreach ($tokens as $index => $token) {
            $start = $this->keyExpressionStart($tokens, $index);

            if ($start === null) {
                continue;
            }

            $isAssignment = ($tokens[$index + 1]->text ?? '') === '=';
            $end = Tokens::expressionEnd($tokens, $start, $isAssignment ? [';'] : [',']);
            [$exprStart, $exprEnd] = $this->followVariable($tokens, $start, $end);
            $randomCalls = $this->randomCalls($tokens, $exprStart, $exprEnd);

            if ($randomCalls === []) {
                continue;
            }

            $expression = mb_strimwidth(Tokens::text($tokens, $start, $end - 1), 0, 160, '…');

            $violations[] = new Violation(
                $this->id(),
                $file->relativePath,
                $token->line,
                "idempotency key = {$expression}",
                'Idempotency key memakai '.implode(', ', $randomCalls).'; pakai identitas bisnis deterministik (KONSEP §A2.5).',
            );
        }

        return $violations;
    }

    /**
     * Start index of the key expression when the token at $index introduces an
     * idempotency key (named argument, array key, variable or property assignment).
     *
     * @param  list<PhpToken>  $tokens
     */
    private function keyExpressionStart(array $tokens, int $index): ?int
    {
        $token = $tokens[$index];
        $next = $tokens[$index + 1] ?? null;

        if ($next === null) {
            return null;
        }

        if ($token->is(T_STRING) && $next->text === ':' && preg_match('/^idempotency_?key$/i', $token->text) === 1
            && in_array($tokens[$index - 1]->text ?? '', ['(', ','], true)) {
            return $index + 2;
        }

        if ($token->is(T_CONSTANT_ENCAPSED_STRING) && $next->is(T_DOUBLE_ARROW)
            && preg_match('/^idempotency_?key$/i', (string) Tokens::stringLiteral($token)) === 1) {
            return $index + 2;
        }

        if ($token->is(T_VARIABLE) && $next->text === '=' && preg_match('/idempotency/i', $token->text) === 1) {
            return $index + 2;
        }

        if ($token->is(T_STRING) && $next->text === '=' && preg_match('/idempotency/i', $token->text) === 1
            && ($tokens[$index - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])) {
            return $index + 2;
        }

        return null;
    }

    /**
     * When the key expression is a single variable, analyse that variable's most
     * recent assignment in the same function instead.
     *
     * @param  list<PhpToken>  $tokens
     * @return array{int, int}
     */
    private function followVariable(array $tokens, int $start, int $end): array
    {
        if ($end - $start !== 1 || ! $tokens[$start]->is(T_VARIABLE)) {
            return [$start, $end];
        }

        $variable = $tokens[$start]->text;

        for ($cursor = $start - 1; $cursor >= 0; $cursor--) {
            if ($tokens[$cursor]->is([T_FUNCTION, T_FN])) {
                break;
            }

            if ($tokens[$cursor]->is(T_VARIABLE) && $tokens[$cursor]->text === $variable && ($tokens[$cursor + 1]->text ?? '') === '=') {
                return [$cursor + 2, Tokens::expressionEnd($tokens, $cursor + 2, [';'])];
            }
        }

        return [$start, $end];
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @return list<string>
     */
    private function randomCalls(array $tokens, int $from, int $to): array
    {
        $calls = [];

        for ($cursor = $from; $cursor < $to; $cursor++) {
            foreach (self::RANDOM_STATIC_CALLS as $class => $methods) {
                foreach ($methods as $method) {
                    if (Tokens::isStaticCall($tokens, $cursor, [$class], $method)) {
                        $calls[] = Tokens::shortName($tokens[$cursor]).'::'.$tokens[$cursor + 2]->text.'()';
                    }
                }
            }

            foreach (self::RANDOM_FUNCTIONS as $function) {
                if (Tokens::isFunctionCall($tokens, $cursor, $function)) {
                    $calls[] = $function.'()';
                }
            }
        }

        return array_values(array_unique($calls));
    }
}
