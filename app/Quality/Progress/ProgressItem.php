<?php

declare(strict_types=1);

namespace App\Quality\Progress;

/**
 * Value object representing a checklist item in docs/PROGRESS.md (P2, R0.4).
 */
final class ProgressItem
{
    /**
     * @param  array<string, list<string>>  $proof  Parsed proof entries grouped by key (commit, file, test, akses, audit, gate, etc.)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $phaseId,
        public readonly bool $isChecked,
        public readonly string $text,
        public readonly array $proof = [],
        public readonly int $lineNumber = 0,
        public readonly string $uniqueKey = '',
    ) {}

    /**
     * Determines if this is a feature item that requires access path and HTTP/command test.
     * Tooling and doc items (such as R0 infra items or items labeled as documents) are exempted.
     */
    public function isFeatureItem(): bool
    {
        if (str_starts_with($this->id, 'R0.')) {
            return false;
        }

        $lower = strtolower($this->text);
        if (str_contains($lower, 'dokumen') || str_contains($lower, 'template pr') || str_contains($lower, 'readme') || str_contains($lower, 'dor')) {
            return false;
        }

        return true;
    }
}
