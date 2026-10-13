<?php

declare(strict_types=1);

namespace App\Quality\Mutation;

/**
 * Resolves touched Action and Service classes from modified file list (PROGRESS R0.12, KONSEP §A14.8, K-MUT).
 */
final class MutationTargetResolver
{
    /**
     * Resolves fully-qualified class names for Action and Service classes from changed file list.
     * Strictly limited to modules/<Module>/Application/** and app/Quality/** (.php, non-migration, non-test).
     *
     * @param  list<string>  $changedFiles
     * @return list<string>
     */
    public static function resolveClasses(array $changedFiles): array
    {
        $classes = [];

        foreach ($changedFiles as $file) {
            $trimmed = trim($file);

            if (! str_ends_with($trimmed, '.php')) {
                continue;
            }

            // Exclude test files, migrations, seeders, config, views
            if (
                str_contains($trimmed, '/tests/')
                || str_starts_with($trimmed, 'tests/')
                || str_ends_with($trimmed, 'Test.php')
                || str_contains($trimmed, '/database/')
                || str_starts_with($trimmed, 'database/')
                || str_contains($trimmed, '/migrations/')
                || str_starts_with($trimmed, 'migrations/')
            ) {
                continue;
            }

            // Exclude interface files
            if (str_ends_with($trimmed, 'Interface.php')) {
                continue;
            }

            // Scope strictly to modules/<Module>/Application/** and app/Quality/**
            $isModuleApp = (bool) preg_match('#^modules/[^/]+/Application/.+\.php$#', $trimmed);
            $isAppQuality = (bool) preg_match('#^app/Quality/.+\.php$#', $trimmed);

            if (! $isModuleApp && ! $isAppQuality) {
                continue;
            }

            // Convert path to fully-qualified class name (FQCN)
            $withoutExt = substr($trimmed, 0, -4);
            $normalized = str_replace('/', '\\', $withoutExt);

            if (str_starts_with($normalized, 'modules\\')) {
                $fqcn = 'Modules\\'.substr($normalized, 8);
            } elseif (str_starts_with($normalized, 'app\\')) {
                $fqcn = 'App\\'.substr($normalized, 4);
            } else {
                $fqcn = $normalized;
            }

            $classes[] = $fqcn;
        }

        $unique = array_values(array_unique($classes));
        sort($unique);

        return $unique;
    }

    /**
     * Resolves the associated test path for a given FQCN using repository path conventions.
     * Avoids running the entire test suite on each class.
     */
    public static function resolveTestPathForClass(string $class, ?string $repoRoot = null): ?string
    {
        $root = $repoRoot ?? (function_exists('app') && app()->has('path.base') ? base_path() : dirname(__DIR__, 3));
        $normalizedClass = ltrim($class, '\\');
        $baseName = class_basename($normalizedClass);

        // 1. Modules: Modules\<Module>\Application\... -> modules/<Module>/tests
        if (str_starts_with($normalizedClass, 'Modules\\')) {
            $parts = explode('\\', $normalizedClass);
            $module = $parts[1] ?? '';
            if ($module !== '') {
                $moduleTestDir = "modules/{$module}/tests";
                if (is_dir($root.'/'.$moduleTestDir)) {
                    $specificFeature = "{$moduleTestDir}/Feature/{$baseName}Test.php";
                    if (is_file($root.'/'.$specificFeature)) {
                        return $specificFeature;
                    }
                    $specificUnit = "{$moduleTestDir}/Unit/{$baseName}Test.php";
                    if (is_file($root.'/'.$specificUnit)) {
                        return $specificUnit;
                    }

                    return $moduleTestDir;
                }
            }
        }

        // 2. App\Quality: App\Quality\<Sub>\<Class>
        if (str_starts_with($normalizedClass, 'App\\Quality\\')) {
            $parts = explode('\\', $normalizedClass);
            $sub = $parts[2] ?? '';

            $candidates = [
                "tests/Unit/Quality/{$sub}/{$baseName}Test.php",
                "tests/Feature/Quality/{$baseName}Test.php",
                "tests/Unit/Quality/{$baseName}Test.php",
                "tests/Feature/Console/{$baseName}Test.php",
            ];

            foreach ($candidates as $cand) {
                if (is_file($root.'/'.$cand)) {
                    return $cand;
                }
            }

            if ($sub !== '' && is_dir($root."/tests/Unit/Quality/{$sub}")) {
                return "tests/Unit/Quality/{$sub}";
            }

            if (is_dir($root.'/tests/Feature/Quality')) {
                return 'tests/Feature/Quality';
            }
        }

        return null;
    }
}
