<?php

declare(strict_types=1);

namespace App\Quality\TestHygiene;

use App\Quality\ArchScan\ArchScanner;
use App\Quality\ArchScan\ScanReport;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;
use App\Quality\Support\SourceFinder;

/**
 * Test hygiene scan T1–T4 (KONSEP.md §A14.7) over `tests/` and `modules/{M}/tests/`.
 */
final class TestHygieneScan
{
    public static function scanProject(string $root): ScanReport
    {
        $moduleTestDirectories = array_map(
            static fn (string $directory): string => substr($directory, strlen(rtrim($root, '/')) + 1),
            glob(rtrim($root, '/').'/modules/*/tests', GLOB_ONLYDIR) ?: [],
        );
        $testFiles = SourceFinder::phpFiles($root, ['tests', ...$moduleTestDirectories]);
        $routeFiles = SourceFinder::phpFiles($root, array_map(
            static fn (string $directory): string => substr($directory, strlen(rtrim($root, '/')) + 1),
            glob(rtrim($root, '/').'/modules/*/routes', GLOB_ONLYDIR) ?: [],
        ));
        $modules = array_map('basename', glob(rtrim($root, '/').'/modules/*', GLOB_ONLYDIR) ?: []);

        $rules = [
            new TrivialAssertionRule,
            new LedgerAccountInTestRule,
            new SkipWithoutBlockerRule,
            new ModuleWithoutHttpTestRule(self::modulesWithHttpTests($testFiles, $modules)),
        ];

        return (new ArchScanner($rules, new ModuleRegistry([])))->scan([...$testFiles, ...$routeFiles]);
    }

    /**
     * Modules with at least one test file that sends an HTTP request. Tests in
     * `tests/Feature/{Module}/` count for that module too.
     *
     * @param  list<SourceFile>  $testFiles
     * @param  list<string>  $modules
     * @return list<string>
     */
    public static function modulesWithHttpTests(array $testFiles, array $modules): array
    {
        $found = [];

        foreach ($testFiles as $file) {
            $module = $file->module() ?? self::moduleOfRootTest($file->relativePath, $modules);

            if ($module !== null && ! isset($found[$module]) && HttpTestDetector::performsHttpRequest($file)) {
                $found[$module] = true;
            }

            $file->releaseTokens();
        }

        $names = array_keys($found);
        sort($names);

        return $names;
    }

    /**
     * `tests/Feature/{Module}/…` or `tests/Feature/{Module}…Test.php` (longest module name wins).
     *
     * @param  list<string>  $modules
     */
    private static function moduleOfRootTest(string $path, array $modules): ?string
    {
        if (preg_match('#^tests/Feature/(?:([^/]+)/)?([^/]+)$#', $path, $matches) !== 1) {
            return null;
        }

        if ($matches[1] !== '' && in_array($matches[1], $modules, true)) {
            return $matches[1];
        }

        $best = null;

        foreach ($modules as $module) {
            if (str_starts_with($matches[2], $module) && ($best === null || strlen($module) > strlen($best))) {
                $best = $module;
            }
        }

        return $best;
    }
}
