<?php

declare(strict_types=1);

namespace App\Quality\Freeze;

use App\Quality\Support\SourceFile;
use App\Quality\Support\Tokens;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Freeze of modules/Integration (KONSEP.md §A14.6, K-05). The module became a
 * dumping ground for 345 domain services and 857 foreign tables. Existing files
 * are listed in a ratchet baseline; new files are only allowed in the external
 * adapter directories, new tables only with the `intg_` prefix.
 */
final class IntegrationFreeze
{
    public const MODULE_PATH = 'modules/Integration';

    /** Directories (relative to the module) where new adapter code may still be added. */
    public const ALLOWED_DIRECTORIES = ['Adapters/', 'Webhooks/', 'Edi/', 'Http/Controllers/Api/', 'tests/'];

    public const ALLOWED_TABLE_PREFIX = 'intg_';

    /**
     * Current frozen entries: `file:{path}` outside allowed directories and
     * `table:{name}` for tables without the `intg_` prefix.
     *
     * @return list<string>
     */
    public static function currentEntries(string $root): array
    {
        $moduleRoot = rtrim($root, '/').'/'.self::MODULE_PATH;
        $files = [];
        $tables = [];

        if (! is_dir($moduleRoot)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $relative = self::MODULE_PATH.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($moduleRoot) + 1));
            $files[] = $relative;

            if (str_contains($relative, '/database/migrations/') && str_ends_with($relative, '.php')) {
                $tables = [...$tables, ...self::createdTables(SourceFile::fromDisk($root, $relative))];
            }
        }

        return self::entriesFrom($files, $tables);
    }

    /**
     * @param  list<string>  $files  paths relative to the project root
     * @param  list<string>  $tables
     * @return list<string>
     */
    public static function entriesFrom(array $files, array $tables): array
    {
        $entries = [];

        foreach ($files as $path) {
            $insideModule = substr($path, strlen(self::MODULE_PATH) + 1);

            foreach (self::ALLOWED_DIRECTORIES as $directory) {
                if (str_starts_with($insideModule, $directory)) {
                    continue 2;
                }
            }

            $entries[] = "file:{$path}";
        }

        foreach ($tables as $table) {
            if (! str_starts_with($table, self::ALLOWED_TABLE_PREFIX)) {
                $entries[] = "table:{$table}";
            }
        }

        $entries = array_values(array_unique($entries));
        sort($entries);

        return $entries;
    }

    /**
     * @return list<string>
     */
    private static function createdTables(SourceFile $migration): array
    {
        $tokens = $migration->tokens();
        $tables = [];

        foreach ($tokens as $index => $token) {
            if (Tokens::isStaticCall($tokens, $index, ['Schema'], 'create') && ($table = Tokens::stringLiteral($tokens[$index + 4] ?? $token)) !== null) {
                $tables[] = $table;
            }
        }

        $migration->releaseTokens();

        return $tables;
    }
}
