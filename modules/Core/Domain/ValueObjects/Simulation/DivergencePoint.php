<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects\Simulation;

/**
 * Value Object titik divergensi antara dua run simulasi.
 */
final readonly class DivergencePoint
{
    /**
     * @param  array<string, array{a: mixed, b: mixed, identical: bool}>  $handlerDiscrepancies
     */
    public function __construct(
        public bool $hasDiverged,
        public ?int $divergenceDay,
        public string $reason,
        public ?string $hashA = null,
        public ?string $hashB = null,
        public array $handlerDiscrepancies = [],
    ) {}

    public static function match(): self
    {
        return new self(
            hasDiverged: false,
            divergenceDay: null,
            reason: 'Kedua sesi memiliki rantai fingerprint identik (deterministik 100%).',
        );
    }

    /**
     * @param  array<string, array{a: mixed, b: mixed, identical: bool}>  $discrepancies
     */
    public static function diverged(
        int $day,
        string $reason,
        string $hashA,
        string $hashB,
        array $discrepancies = [],
    ): self {
        return new self(
            hasDiverged: true,
            divergenceDay: $day,
            reason: $reason,
            hashA: $hashA,
            hashB: $hashB,
            handlerDiscrepancies: $discrepancies,
        );
    }

    /**
     * @return array{
     *     diverged: bool,
     *     first_divergence_day: int|null,
     *     reason: string,
     *     hash_a: string|null,
     *     hash_b: string|null,
     *     handler_diffs: array<string, array{a: mixed, b: mixed, identical: bool}>
     * }
     */
    public function toArray(): array
    {
        return [
            'diverged' => $this->hasDiverged,
            'first_divergence_day' => $this->divergenceDay,
            'reason' => $this->reason,
            'hash_a' => $this->hashA,
            'hash_b' => $this->hashB,
            'handler_diffs' => $this->handlerDiscrepancies,
        ];
    }
}
