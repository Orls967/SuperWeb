<?php

declare(strict_types=1);

namespace App\Quality\Commands;

use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;
use PhpToken;

/**
 * Finds every Artisan command name declared in source code: `$signature` / `$name`
 * properties of Command classes, `#[AsCommand]` / `#[Signature]` attributes and
 * `Artisan::command()` closures. The container keeps only the last registration of
 * a duplicated name, so duplicates must be caught statically (K-02: `api:audit`).
 */
final class CommandNameCollector
{
    /**
     * @param  iterable<SourceFile>  $files
     * @return list<array{name: string, path: string, line: int}>
     */
    public static function collect(iterable $files): array
    {
        $commands = [];

        foreach ($files as $file) {
            if (! str_contains($file->contents, 'Command')
                && ! str_contains($file->contents, 'Artisan')
                && ! str_contains($file->contents, 'Signature')) {
                continue;
            }

            $tokens = $file->tokens();
            $isCommandClass = self::extendsCommand($tokens);

            foreach ($tokens as $index => $token) {
                $literal = null;

                if ($isCommandClass && self::isCommandProperty($tokens, $index)) {
                    $literal = $tokens[$index + 2] ?? null;
                } elseif ($token->is(T_ATTRIBUTE) && isset($tokens[$index + 2])
                    && in_array(Tokens::shortName($tokens[$index + 1]), ['AsCommand', 'Signature'], true)
                    && $tokens[$index + 2]->text === '(') {
                    $literal = self::firstStringArgument($tokens, $index + 2);
                } elseif (Tokens::isStaticCall($tokens, $index, ['Artisan'], 'command')) {
                    $literal = $tokens[$index + 4] ?? null;
                }

                $name = $literal === null ? null : self::commandName($literal);

                if ($name !== null) {
                    $commands[] = ['name' => $name, 'path' => $file->relativePath, 'line' => $token->line];
                }
            }

            $file->releaseTokens();
        }

        return $commands;
    }

    /**
     * @param  list<array{name: string, path: string, line: int}>  $commands
     * @return array<string, list<string>> command name => declarations (`path:line`)
     */
    public static function duplicates(array $commands): array
    {
        $byName = [];

        foreach ($commands as $command) {
            $byName[$command['name']][] = "{$command['path']}:{$command['line']}";
        }

        return array_filter($byName, static fn (array $declarations): bool => count($declarations) > 1);
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private static function extendsCommand(array $tokens): bool
    {
        foreach ($tokens as $index => $token) {
            if ($token->is(T_EXTENDS) && isset($tokens[$index + 1]) && Tokens::shortName($tokens[$index + 1]) === 'Command') {
                return true;
            }
        }

        return false;
    }

    /**
     * `protected $signature = '…'` or `protected $name = '…'` (optionally typed).
     *
     * @param  list<PhpToken>  $tokens
     */
    private static function isCommandProperty(array $tokens, int $index): bool
    {
        $token = $tokens[$index];

        if (! $token->is(T_VARIABLE) || ! in_array($token->text, ['$signature', '$name'], true) || ($tokens[$index + 1]->text ?? '') !== '=') {
            return false;
        }

        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $previous = $tokens[$cursor];

            if ($previous->is([T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR])) {
                return true;
            }

            if (! $previous->is([T_STRING, T_STATIC, T_READONLY]) && $previous->text !== '?') {
                return false;
            }
        }

        return false;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private static function firstStringArgument(array $tokens, int $open): ?PhpToken
    {
        $close = Tokens::matchingClose($tokens, $open) ?? count($tokens) - 1;

        for ($cursor = $open + 1; $cursor < $close; $cursor++) {
            if ($tokens[$cursor]->is(T_CONSTANT_ENCAPSED_STRING)) {
                return $tokens[$cursor];
            }
        }

        return null;
    }

    private static function commandName(PhpToken $literal): ?string
    {
        $value = Tokens::stringLiteral($literal);

        if ($value === null) {
            return null;
        }

        $name = preg_split('/[\s{]/', trim($value), 2)[0] ?? '';

        return $name === '' ? null : $name;
    }
}
