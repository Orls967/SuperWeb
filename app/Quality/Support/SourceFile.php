<?php

declare(strict_types=1);

namespace App\Quality\Support;

use PhpToken;
use RuntimeException;

/**
 * A PHP file loaded for static analysis.
 *
 * Paths are always relative to the project root with forward slashes so that
 * findings (and baseline fingerprints) are identical on every machine.
 */
final class SourceFile
{
    /** @var list<PhpToken>|null */
    private ?array $significantTokens = null;

    public function __construct(
        public readonly string $relativePath,
        public readonly string $contents,
    ) {}

    public static function fromDisk(string $basePath, string $relativePath): self
    {
        $absolutePath = rtrim($basePath, '/').'/'.ltrim($relativePath, '/');
        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            throw new RuntimeException("Tidak bisa membaca file {$absolutePath}.");
        }

        return new self(str_replace('\\', '/', ltrim($relativePath, '/')), $contents);
    }

    /**
     * Tokens without whitespace, comments, docblocks and the open tag.
     *
     * @return list<PhpToken>
     */
    public function tokens(): array
    {
        return $this->significantTokens ??= array_values(array_filter(
            PhpToken::tokenize($this->contents),
            static fn (PhpToken $token): bool => ! $token->isIgnorable(),
        ));
    }

    /**
     * Releases cached tokens so long-running scans over thousands of files
     * do not exhaust the PHP memory limit.
     */
    public function releaseTokens(): void
    {
        $this->significantTokens = null;
    }

    /**
     * Module name for files under `modules/{Module}/` (also when the scanned tree
     * lives in a sub-directory, e.g. a test fixture), otherwise null.
     */
    public function module(): ?string
    {
        if (preg_match('#(?:^|/)modules/([^/]+)/#', $this->relativePath, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public function isMigration(): bool
    {
        return str_contains($this->relativePath, '/database/migrations/');
    }

    public function isTest(): bool
    {
        return str_starts_with($this->relativePath, 'tests/')
            || preg_match('#(?:^|/)modules/[^/]+/tests/#', $this->relativePath) === 1;
    }

    /**
     * True when the path (relative to the module root) starts with the given segment,
     * e.g. `Application/` for `modules/Hcm/Application/Services/HcmService.php`.
     */
    public function isInModuleDirectory(string $directory): bool
    {
        return preg_match('#(?:^|/)modules/[^/]+/'.preg_quote(trim($directory, '/'), '#').'/#', $this->relativePath) === 1;
    }
}
