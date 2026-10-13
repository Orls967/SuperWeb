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
     * Resolves item type based on P2 `jenis:` proof key.
     * Default without explicit jenis is 'fitur'.
     */
    public function itemType(): string
    {
        $kinds = $this->proof['jenis'] ?? [];
        if (! empty($kinds)) {
            $raw = strtolower(trim($kinds[0]));
            if (preg_match('/^(tooling|konfigurasi|dokumen|fitur)\b/i', $raw, $matches)) {
                return strtolower($matches[1]);
            }

            return $raw;
        }

        return 'fitur';
    }

    /**
     * Determines if this is a feature item (default or jenis: fitur).
     */
    public function isFeatureItem(): bool
    {
        return $this->itemType() === 'fitur';
    }
}
