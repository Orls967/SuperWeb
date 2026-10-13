<?php

declare(strict_types=1);

namespace App\Quality\Support;

use PhpToken;

/**
 * Extracts typed function parameters and class properties from tokens.
 */
final class Declarations
{
    /**
     * Parameters of every function, method, closure and arrow function
     * (promoted constructor properties included).
     *
     * @return list<array{function: string, name: string, types: list<string>, attributes: string, line: int}>
     */
    public static function parameters(SourceFile $file): array
    {
        $tokens = $file->tokens();
        $parameters = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (! $tokens[$index]->is([T_FUNCTION, T_FN])) {
                continue;
            }

            $cursor = $index + 1;

            if (isset($tokens[$cursor]) && $tokens[$cursor]->text === '&') {
                $cursor++;
            }

            $function = '{closure}';

            if (isset($tokens[$cursor]) && $tokens[$cursor]->is(T_STRING)) {
                $function = $tokens[$cursor]->text;
                $cursor++;
            }

            if (! isset($tokens[$cursor]) || $tokens[$cursor]->text !== '(') {
                continue;
            }

            $close = Tokens::matchingClose($tokens, $cursor);

            if ($close === null) {
                continue;
            }

            $start = $cursor + 1;

            while ($start < $close) {
                $end = Tokens::expressionEnd($tokens, $start, [',']);
                $parameter = self::parameter($tokens, $start, min($end, $close) - 1, $function);

                if ($parameter !== null) {
                    $parameters[] = $parameter;
                }

                $start = $end + 1;
            }
        }

        return $parameters;
    }

    /**
     * Typed class properties declared with a modifier (`public`, `protected`, `private`,
     * `var`, `readonly`, `static`). Promoted constructor properties are reported by
     * parameters(), not here.
     *
     * @return list<array{name: string, types: list<string>, line: int}>
     */
    public static function properties(SourceFile $file): array
    {
        $tokens = $file->tokens();
        $properties = [];
        $parenthesisDepth = 0;
        $braces = []; // stack of 'class' | 'other'
        $nextBraceIsClassBody = false;
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token->is([T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM]) && ! ($tokens[$index - 1] ?? null)?->is(T_DOUBLE_COLON)) {
                $nextBraceIsClassBody = true;

                continue;
            }

            if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $braces[] = $nextBraceIsClassBody && $token->text === '{' ? 'class' : 'other';
                $nextBraceIsClassBody = false;

                continue;
            }

            if ($token->text === '}') {
                array_pop($braces);

                continue;
            }

            if ($token->text === '(') {
                $parenthesisDepth++;

                continue;
            }

            if ($token->text === ')') {
                $parenthesisDepth--;

                continue;
            }

            if ($parenthesisDepth !== 0 || end($braces) !== 'class' || ! self::isModifier($token)) {
                continue;
            }

            $types = [];
            $cursor = $index + 1;

            while (isset($tokens[$cursor])) {
                $next = $tokens[$cursor];

                if (self::isModifier($next)) {
                    $cursor++;

                    continue;
                }

                if ($next->is(T_VARIABLE)) {
                    $properties[] = ['name' => substr($next->text, 1), 'types' => $types, 'line' => $next->line];
                    $index = $cursor; // only modifiers/types were skipped, never brackets

                    break;
                }

                if (Tokens::isNameToken($next) || $next->is([T_ARRAY, T_CALLABLE])) {
                    $types[] = strtolower(Tokens::name($next));
                    $cursor++;

                    continue;
                }

                if (in_array($next->text, ['?', '|', '&'], true)) {
                    $cursor++;

                    continue;
                }

                break; // `function`, `const`, `::`, `fn`, `(`, ... — not a property declaration
            }
        }

        return $properties;
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @return array{function: string, name: string, types: list<string>, attributes: string, line: int}|null
     */
    private static function parameter(array $tokens, int $from, int $to, string $function): ?array
    {
        $attributes = '';
        $types = [];

        for ($cursor = $from; $cursor <= $to; $cursor++) {
            $token = $tokens[$cursor];

            if ($token->is(T_ATTRIBUTE)) {
                $close = Tokens::matchingClose($tokens, $cursor) ?? $to;
                $attributes .= Tokens::text($tokens, $cursor, $close);
                $cursor = $close;

                continue;
            }

            if ($token->is(T_VARIABLE)) {
                return [
                    'function' => $function,
                    'name' => substr($token->text, 1),
                    'types' => $types,
                    'attributes' => $attributes,
                    'line' => $token->line,
                ];
            }

            if (Tokens::isNameToken($token) || $token->is([T_ARRAY, T_CALLABLE, T_STATIC])) {
                $types[] = strtolower(Tokens::name($token));
            }
        }

        return null;
    }

    private static function isModifier(PhpToken $token): bool
    {
        if ($token->is([T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR, T_READONLY, T_STATIC])) {
            return true;
        }

        foreach (['T_PUBLIC_SET', 'T_PROTECTED_SET', 'T_PRIVATE_SET'] as $constant) {
            if (defined($constant) && $token->is(constant($constant))) {
                return true;
            }
        }

        return false;
    }
}
