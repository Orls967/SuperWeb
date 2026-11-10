<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects\Simulation;

use InvalidArgumentException;

/**
 * Value Object representasi snapshot state sebuah entitas Digital Twin.
 */
final readonly class TwinSnapshotState
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $entityType,
        public string $entityId,
        public int $dayNumber,
        public string $stateHash,
        public array $payload,
    ) {
        if (trim($this->entityType) === '' || trim($this->entityId) === '') {
            throw new InvalidArgumentException('Tipe dan ID entitas digital twin tidak boleh kosong.');
        }
        if ($this->dayNumber < 0) {
            throw new InvalidArgumentException("Nomor hari snapshot harus >= 0: {$this->dayNumber}");
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function create(string $type, string $id, int $day, array $payload): self
    {
        ksort($payload);
        $canonicalJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', "twin-snapshot|{$type}|{$id}|{$day}|{$canonicalJson}");

        return new self($type, $id, $day, $hash, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'day_number' => $this->dayNumber,
            'state_hash' => $this->stateHash,
            'payload' => $this->payload,
        ];
    }
}
