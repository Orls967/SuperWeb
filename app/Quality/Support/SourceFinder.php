<?php

declare(strict_types=1);

namespace App\Quality\Support;

use Symfony\Component\Finder\Finder;

/**
 * Finds PHP source files (excluding Blade views) in a deterministic order.
 */
final class SourceFinder
{
    /**
     * @param  list<string>  $directories  paths relative to $basePath
     * @param  list<string>  $excludePatterns  regular expressions matched against the relative path
     * @return list<SourceFile>
     */
    public static function phpFiles(string $basePath, array $directories, array $excludePatterns = []): array
    {
        $basePath = rtrim($basePath, '/');
        $existing = array_values(array_filter(
            array_map(static fn (string $directory): string => $basePath.'/'.trim($directory, '/'), $directories),
            'is_dir',
        ));

        if ($existing === []) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->in($existing)
            ->name('*.php')
            ->notName('*.blade.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->sortByName();

        $files = [];

        foreach ($finder as $file) {
            $relativePath = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($basePath))), '/');

            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $relativePath) === 1) {
                    continue 2;
                }
            }

            $files[] = new SourceFile($relativePath, $file->getContents());
        }

        usort($files, static fn (SourceFile $a, SourceFile $b): int => strcmp($a->relativePath, $b->relativePath));

        return $files;
    }
}
