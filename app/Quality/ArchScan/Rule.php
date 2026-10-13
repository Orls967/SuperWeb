<?php

declare(strict_types=1);

namespace App\Quality\ArchScan;

use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\SourceFile;

/**
 * An `arch:scan` rule (KONSEP.md §A14.1).
 */
interface Rule
{
    /**
     * Stable identifier used in baselines, e.g. `A1`.
     */
    public function id(): string;

    /**
     * One-line description shown in the command output.
     */
    public function description(): string;

    /**
     * @return list<Violation>
     */
    public function check(SourceFile $file, ModuleRegistry $registry): array;
}
