<?php

declare(strict_types=1);

namespace App\Quality\Support;

/**
 * Collects class names a file depends on: `use` imports (including group and
 * trait imports) and fully qualified names written in code.
 */
final class NameReferences
{
    /**
     * @return list<array{name: string, alias: string, line: int}>
     */
    public static function imports(SourceFile $file): array
    {
        $tokens = $file->tokens();
        $imports = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (! $tokens[$index]->is(T_USE)) {
                continue;
            }

            $start = $index + 1;

            if (isset($tokens[$start]) && $tokens[$start]->text === '(') {
                continue; // closure `use (...)`
            }

            if (isset($tokens[$start]) && $tokens[$start]->is([T_FUNCTION, T_CONST])) {
                $start++;
            }

            $end = Tokens::expressionEnd($tokens, $start, [';']);
            $groupPrefix = null;

            for ($cursor = $start; $cursor < $end; $cursor++) {
                $token = $tokens[$cursor];

                if ($token->text === '{' && $groupPrefix === null) {
                    // trait conflict-resolution block: `use A, B { ... }`
                    break;
                }

                if (! Tokens::isNameToken($token)) {
                    continue;
                }

                if (isset($tokens[$cursor - 1]) && $tokens[$cursor - 1]->is(T_AS)) {
                    continue;
                }

                $next = $tokens[$cursor + 1] ?? null;

                if ($next !== null && $next->is(T_NS_SEPARATOR) && ($tokens[$cursor + 2]->text ?? null) === '{') {
                    $groupPrefix = Tokens::name($token);
                    $cursor += 2;

                    continue;
                }

                $name = $groupPrefix !== null ? $groupPrefix.'\\'.Tokens::name($token) : Tokens::name($token);
                $alias = Tokens::shortName($token);

                if ($next !== null && $next->is(T_AS) && isset($tokens[$cursor + 2])) {
                    $alias = $tokens[$cursor + 2]->text;
                }

                $imports[] = ['name' => $name, 'alias' => $alias, 'line' => $token->line];
            }

            $index = $end;
        }

        return $imports;
    }

    /**
     * Fully qualified names used in code, e.g. `\Modules\Mall\Domain\Models\Tenant::class`.
     *
     * @return list<array{name: string, line: int}>
     */
    public static function fullyQualified(SourceFile $file): array
    {
        $references = [];

        foreach ($file->tokens() as $token) {
            if ($token->is(T_NAME_FULLY_QUALIFIED)) {
                $references[] = ['name' => Tokens::name($token), 'line' => $token->line];
            }
        }

        return $references;
    }

    /**
     * Resolve a short or aliased class name to its imported fully qualified name.
     */
    public static function resolve(SourceFile $file, string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $first = explode('\\', $name)[0];

        foreach (self::imports($file) as $import) {
            if (strcasecmp($import['alias'], $first) === 0) {
                $rest = substr($name, strlen($first));

                return $import['name'].$rest;
            }
        }

        return $name;
    }
}
