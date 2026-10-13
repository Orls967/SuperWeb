<?php

declare(strict_types=1);

namespace App\Quality\Mutation;

/**
 * Resolves touched Action and Service classes from modified file list (PROGRESS R0.12, KONSEP §A14.8).
 */
final class MutationTargetResolver
{
    /**
     * Resolves fully-qualified class names for Action and Service classes from changed file list.
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

            // Exclude test files, migrations, config, and views
            if (
                str_contains($trimmed, '/tests/')
                || str_starts_with($trimmed, 'tests/')
                || str_contains($trimmed, '/database/')
                || str_starts_with($trimmed, 'database/')
                || str_contains($trimmed, '/config/')
                || str_starts_with($trimmed, 'config/')
            ) {
                continue;
            }

            // Target only Action and Service classes in modules or app
            $isActionOrService = str_contains($trimmed, '/Application/Actions/')
                || str_contains($trimmed, '/Application/Services/')
                || str_contains($trimmed, '/Actions/')
                || str_contains($trimmed, '/Services/')
                || str_contains($trimmed, 'app/Quality/');

            if (! $isActionOrService) {
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
}
