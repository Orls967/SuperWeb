<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object immutable spesifikasi konfigurasi aturan penyamaran data PII.
 */
final class MaskingSpecification
{
    public const STRATEGIES = ['email', 'phone', 'nik', 'address', 'account_number', 'redact', 'blind_index'];

    public function __construct(
        public readonly string $domain,
        public readonly string $columnPattern,
        public readonly string $strategy,
        public readonly bool $preserveFormat = true,
        public readonly ?string $replacementToken = '[MASKED]'
    ) {
        if (! in_array($this->strategy, self::STRATEGIES, true)) {
            throw new InvalidArgumentException("Strategi penyamaran tidak valid: {$this->strategy}");
        }
    }

    public function matchesColumn(string $columnName): bool
    {
        return str_contains(strtolower($columnName), strtolower($this->columnPattern));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'column_pattern' => $this->columnPattern,
            'strategy' => $this->strategy,
            'preserve_format' => $this->preserveFormat,
            'replacement_token' => $this->replacementToken,
        ];
    }
}
