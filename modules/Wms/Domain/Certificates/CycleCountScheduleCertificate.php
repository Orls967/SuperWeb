<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

/**
 * Sertifikat hasil penjadwalan CycleCountScheduler (H3).
 */
final readonly class CycleCountScheduleCertificate
{
    /**
     * @param  list<array{id: string, abcClass: string, overdue: int}>  $selected
     * @param  list<array{id: string, abcClass: string, overdue: int}>  $carriedOver
     */
    public function __construct(
        public int $today,
        public int $capacity,
        public array $selected,
        public array $carriedOver,
        public string $signatureHash,
    ) {}

    /**
     * @param  list<array{id: string, abcClass: string, overdue: int}>  $selected
     * @param  list<array{id: string, abcClass: string, overdue: int}>  $carriedOver
     */
    public static function create(int $today, int $capacity, array $selected, array $carriedOver): self
    {
        $payload = json_encode([
            'today' => $today,
            'capacity' => $capacity,
            'selected' => $selected,
            'carried_over' => $carriedOver,
        ], JSON_THROW_ON_ERROR);

        return new self(
            today: $today,
            capacity: $capacity,
            selected: $selected,
            carriedOver: $carriedOver,
            signatureHash: hash('sha256', $payload),
        );
    }
}
