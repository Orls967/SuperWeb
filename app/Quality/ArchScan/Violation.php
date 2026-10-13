<?php

declare(strict_types=1);

namespace App\Quality\ArchScan;

/**
 * One rule violation. The signature describes WHAT is wrong (e.g. the imported class)
 * without the line number, so the baseline fingerprint survives unrelated edits.
 */
final class Violation
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $path,
        public readonly int $line,
        public readonly string $signature,
        public readonly string $message,
    ) {}

    /**
     * Baseline key: `{path} :: {signature} #{occurrence}`.
     */
    public function fingerprint(int $occurrence): string
    {
        return "{$this->path} :: {$this->signature} #{$occurrence}";
    }
}
