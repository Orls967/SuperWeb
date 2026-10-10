<?php

declare(strict_types=1);

namespace App\Quality\Autoload;

use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;

/**
 * Finds classes whose file path does not match their namespace under the PSR-4
 * map, compared case-sensitively. Such classes load on case-insensitive
 * filesystems (macOS/Windows) but are missing on Linux servers and CI (K-28).
 */
final class Psr4ComplianceChecker
{
    /**
     * @param  array<string, string>  $psr4  namespace prefix (with trailing `\`) => directory relative to the project root
     */
    public function __construct(private readonly array $psr4) {}

    /**
     * @param  iterable<SourceFile>  $files
     * @return list<array{class: string, path: string, expected: string}>
     */
    public function mismatches(iterable $files): array
    {
        $mismatches = [];

        foreach ($files as $file) {
            $class = self::declaredClass($file);

            if ($class === null) {
                continue;
            }

            $expected = $this->expectedPath($class);

            if ($expected !== null && $expected !== $file->relativePath) {
                $mismatches[] = ['class' => $class, 'path' => $file->relativePath, 'expected' => $expected];
            }
        }

        return $mismatches;
    }

    public function expectedPath(string $class): ?string
    {
        $best = null;

        foreach ($this->psr4 as $prefix => $directory) {
            if (str_starts_with($class, $prefix) && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        if ($best === null) {
            return null;
        }

        return rtrim($this->psr4[$best], '/').'/'.str_replace('\\', '/', substr($class, strlen($best))).'.php';
    }

    /**
     * Fully qualified name of the first named class, interface, trait or enum in the file.
     */
    private static function declaredClass(SourceFile $file): ?string
    {
        $tokens = $file->tokens();
        $namespace = '';

        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE) && isset($tokens[$index + 1]) && Tokens::isNameToken($tokens[$index + 1])) {
                $namespace = Tokens::name($tokens[$index + 1]);
            }

            $previous = $tokens[$index - 1] ?? null;

            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])
                && ! ($previous?->is([T_DOUBLE_COLON, T_NEW]) ?? false)
                && ($tokens[$index + 1] ?? null)?->is(T_STRING)) {
                return ltrim($namespace.'\\'.$tokens[$index + 1]->text, '\\');
            }
        }

        return null;
    }
}
