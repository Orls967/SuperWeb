<?php

declare(strict_types=1);

namespace App\Quality\Support;

use PhpToken;

/**
 * Small helpers for walking significant PHP tokens (see SourceFile::tokens()).
 */
final class Tokens
{
    /**
     * Index of the bracket that closes the opener at $openIndex, or null when unbalanced.
     *
     * @param  list<PhpToken>  $tokens
     */
    public static function matchingClose(array $tokens, int $openIndex): ?int
    {
        $depth = 0;
        $count = count($tokens);

        for ($index = $openIndex; $index < $count; $index++) {
            if (self::isOpener($tokens[$index])) {
                $depth++;
            } elseif (self::isCloser($tokens[$index])) {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Index of the first token at bracket depth 0 whose text is one of $terminators,
     * starting at $start. An unbalanced closing bracket also ends the expression
     * (e.g. the `)` that closes an argument list). Returns count($tokens) when
     * neither is found.
     *
     * @param  list<PhpToken>  $tokens
     * @param  list<string>  $terminators
     */
    public static function expressionEnd(array $tokens, int $start, array $terminators): int
    {
        $depth = 0;
        $count = count($tokens);

        for ($index = $start; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($depth === 0 && in_array($token->text, $terminators, true)) {
                return $index;
            }

            if (self::isOpener($token)) {
                $depth++;
            } elseif (self::isCloser($token)) {
                if ($depth === 0) {
                    return $index;
                }

                $depth--;
            }
        }

        return $count;
    }

    /**
     * Source-like text of tokens $from..$to (inclusive) with whitespace normalised.
     *
     * @param  list<PhpToken>  $tokens
     */
    public static function text(array $tokens, int $from, int $to): string
    {
        $text = '';

        for ($index = $from; $index <= $to && $index < count($tokens); $index++) {
            $piece = $tokens[$index]->text;

            if ($text !== '' && self::isWordChar(substr($text, -1)) && self::isWordChar($piece[0])) {
                $text .= ' ';
            }

            $text .= $piece;
        }

        return $text;
    }

    /**
     * Name text without a leading backslash (works for T_STRING and qualified names).
     */
    public static function name(PhpToken $token): string
    {
        return ltrim($token->text, '\\');
    }

    public static function isNameToken(PhpToken $token): bool
    {
        return $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE]);
    }

    /**
     * Last segment of a (possibly qualified) name: `Illuminate\Support\Str` → `Str`.
     */
    public static function shortName(PhpToken $token): string
    {
        $name = self::name($token);
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }

    /**
     * True when tokens at $index form `Class::method(` with the given short class
     * names (case-insensitive) and method name.
     *
     * @param  list<PhpToken>  $tokens
     * @param  list<string>  $classShortNames
     */
    public static function isStaticCall(array $tokens, int $index, array $classShortNames, string $method): bool
    {
        if (! isset($tokens[$index + 3]) || ! self::isNameToken($tokens[$index])) {
            return false;
        }

        $shortName = strtolower(self::shortName($tokens[$index]));

        return in_array($shortName, array_map('strtolower', $classShortNames), true)
            && $tokens[$index + 1]->is(T_DOUBLE_COLON)
            && $tokens[$index + 2]->is(T_STRING)
            && strcasecmp($tokens[$index + 2]->text, $method) === 0
            && $tokens[$index + 3]->text === '(';
    }

    /**
     * True when tokens at $index form a plain function call `name(` that is not a
     * method call, static call, or function declaration.
     *
     * @param  list<PhpToken>  $tokens
     */
    public static function isFunctionCall(array $tokens, int $index, string $function): bool
    {
        if (! isset($tokens[$index + 1]) || ! self::isNameToken($tokens[$index])) {
            return false;
        }

        if (strcasecmp(self::name($tokens[$index]), $function) !== 0 || $tokens[$index + 1]->text !== '(') {
            return false;
        }

        $previous = $tokens[$index - 1] ?? null;

        return $previous === null
            || ! $previous->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW]);
    }

    /**
     * Unquoted value of a constant string token such as `'abc'` or `"abc"`; null for
     * anything else (including interpolated strings).
     */
    public static function stringLiteral(PhpToken $token): ?string
    {
        if (! $token->is(T_CONSTANT_ENCAPSED_STRING)) {
            return null;
        }

        $quote = $token->text[0];
        $inner = substr($token->text, 1, -1);

        return $quote === "'"
            ? str_replace(["\\'", '\\\\'], ["'", '\\'], $inner)
            : stripcslashes($inner);
    }

    private static function isOpener(PhpToken $token): bool
    {
        return in_array($token->text, ['(', '[', '{'], true)
            || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE]);
    }

    private static function isCloser(PhpToken $token): bool
    {
        return in_array($token->text, [')', ']', '}'], true);
    }

    private static function isWordChar(string $character): bool
    {
        return preg_match('/[A-Za-z0-9_$\\\\]/', $character) === 1;
    }
}
