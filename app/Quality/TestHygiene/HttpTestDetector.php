<?php

declare(strict_types=1);

namespace App\Quality\TestHygiene;

use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;
use PhpToken;

/**
 * Static check whether a test file sends HTTP requests through Laravel's testing
 * helpers (`$this->get(...)`, `->actingAs($user)->post(route(...))`, Pest's
 * `get('/…')`), as opposed to calling services directly.
 */
final class HttpTestDetector
{
    private const HTTP_METHODS = [
        'get', 'post', 'put', 'patch', 'delete', 'options', 'call', 'json',
        'getjson', 'postjson', 'putjson', 'patchjson', 'deletejson', 'optionsjson',
    ];

    public static function performsHttpRequest(SourceFile $file): bool
    {
        $tokens = $file->tokens();

        foreach ($tokens as $index => $token) {
            if (! $token->is(T_STRING) || ! in_array(strtolower($token->text), self::HTTP_METHODS, true) || ($tokens[$index + 1]->text ?? '') !== '(') {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;
            // json()/call() take the HTTP method first and the URI second.
            $uriArgument = in_array(strtolower($token->text), ['json', 'call'], true)
                ? Tokens::expressionEnd($tokens, $index + 2, [',']) + 1
                : $index + 2;

            if ($previous !== null && $previous->is(T_OBJECT_OPERATOR)) {
                if (($tokens[$index - 2] ?? null)?->text === '$this' || self::isUrlArgument($tokens, $uriArgument)) {
                    return true;
                }

                continue;
            }

            $isPlainFunction = $previous === null || ! $previous->is([T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR, T_FUNCTION, T_NEW]);

            if ($isPlainFunction && self::isUrlArgument($tokens, $uriArgument)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `'/path'`, `"/path/{$id}"`, `route(...)`, `url(...)` or `action(...)`.
     *
     * @param  list<PhpToken>  $tokens
     */
    private static function isUrlArgument(array $tokens, int $index): bool
    {
        $first = $tokens[$index] ?? null;

        if ($first === null) {
            return false;
        }

        $literal = Tokens::stringLiteral($first);

        if ($literal !== null) {
            return str_starts_with($literal, '/');
        }

        if ($first->text === '"') {
            return str_starts_with($tokens[$index + 1]->text ?? '', '/');
        }

        return $first->is(T_STRING) && in_array(strtolower($first->text), ['route', 'url', 'action'], true)
            && ($tokens[$index + 1]->text ?? '') === '(';
    }
}
