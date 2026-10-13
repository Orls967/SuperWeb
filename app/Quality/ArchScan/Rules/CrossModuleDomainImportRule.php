<?php

declare(strict_types=1);

namespace App\Quality\ArchScan\Rules;

use App\Quality\ArchScan\Rule;
use App\Quality\ArchScan\Violation;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\NameReferences;
use App\Quality\Support\SourceFile;

/**
 * A1 — a module uses another module's `Domain` namespace (X16, K-07).
 * Other modules must go through the owner's Contract, events, the Ledger or the PaymentGateway.
 */
final class CrossModuleDomainImportRule implements Rule
{
    public function id(): string
    {
        return 'A1';
    }

    public function description(): string
    {
        return 'Import/penggunaan Modules\{Lain}\Domain\* dari modul lain';
    }

    public function check(SourceFile $file, ModuleRegistry $registry): array
    {
        $module = $file->module();

        if ($module === null) {
            return [];
        }

        $violations = [];

        foreach (NameReferences::imports($file) as $import) {
            $violation = $this->violationFor($file, $registry, $module, $import['name'], $import['line'], 'use');

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        foreach (NameReferences::fullyQualified($file) as $reference) {
            $violation = $this->violationFor($file, $registry, $module, $reference['name'], $reference['line'], 'ref');

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        return $violations;
    }

    private function violationFor(SourceFile $file, ModuleRegistry $registry, string $module, string $name, int $line, string $kind): ?Violation
    {
        if (preg_match('#^Modules\\\\([^\\\\]+)\\\\Domain(\\\\|$)#', $name, $matches) !== 1) {
            return null;
        }

        $owner = $matches[1];

        if ($owner === $module || $registry->isSharedKernelNamespace($name)) {
            return null;
        }

        return new Violation(
            $this->id(),
            $file->relativePath,
            $line,
            "{$kind} {$name}",
            "Modul {$module} memakai Domain milik {$owner} ({$name}); pakai Contract/event milik {$owner}.",
        );
    }
}
