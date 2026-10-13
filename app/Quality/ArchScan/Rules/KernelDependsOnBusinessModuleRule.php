<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\NameReferences;
use App\Quality\Support\SourceFile;

/**
 * A9 — a kernel module (Shared, Core) depends on a business module (K-07). The
 * kernel must stay at the bottom of the dependency graph; business modules
 * register themselves into kernel registries instead.
 */
final class KernelDependsOnBusinessModuleRule implements Rule
{
    public function id(): string
    {
        return 'A9';
    }

    public function description(): string
    {
        return 'Modul kernel (Shared/Core) memakai namespace modul bisnis';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        $module = $file->module();

        if ($module === null || ! $registry->isKernelModule($module)) {
            return [];
        }

        $references = [
            ...array_map(static fn (array $import): array => ['kind' => 'use', 'name' => $import['name'], 'line' => $import['line']], NameReferences::imports($file)),
            ...array_map(static fn (array $reference): array => ['kind' => 'ref', 'name' => $reference['name'], 'line' => $reference['line']], NameReferences::fullyQualified($file)),
        ];

        $violations = [];

        foreach ($references as $reference) {
            if (preg_match('#^Modules\\\\([^\\\\]+)\\\\#', $reference['name'], $matches) !== 1) {
                continue;
            }

            $target = $matches[1];

            if ($registry->isKernelModule($target) || $registry->isSharedKernelNamespace($reference['name'])) {
                continue;
            }

            $violations[] = new Violation(
                $this->id(),
                $file->relativePath,
                $reference['line'],
                "{$reference['kind']} {$reference['name']}",
                "Modul kernel {$module} bergantung pada modul bisnis {$target}.",
            );
        }

        return $violations;
    }
}
