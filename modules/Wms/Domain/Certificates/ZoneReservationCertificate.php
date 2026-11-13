<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

use InvalidArgumentException;

/**
 * Sertifikat deterministik & verifier untuk penjadwalan reservasi zona J1 (PR2).
 */
final readonly class ZoneReservationCertificate
{
    /**
     * @param  array<string, mixed>  $canonicalInput
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>  $result
     */
    public function __construct(
        public array $canonicalInput,
        public array $steps,
        public array $result,
        public string $sha256,
    ) {}

    /**
     * @param  array<string, mixed>  $canonicalInput
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>  $result
     */
    public static function issue(array $canonicalInput, array $steps, array $result): self
    {
        $hash = self::computeHash($canonicalInput, $steps, $result);

        return new self($canonicalInput, $steps, $result, $hash);
    }

    public function verifyOrFail(): void
    {
        $expected = self::computeHash($this->canonicalInput, $this->steps, $this->result);
        if (! hash_equals($expected, $this->sha256)) {
            throw new InvalidArgumentException('Sertifikat ZoneReservationCertificate rusak: hash SHA-256 tidak cocok.');
        }

        $start = (int) ($this->result['start'] ?? -1);
        $end = (int) ($this->result['end'] ?? -1);
        $duration = (int) ($this->canonicalInput['duration'] ?? 0);
        $earliest = (int) ($this->canonicalInput['earliest_start'] ?? 0);
        $capacity = (int) ($this->canonicalInput['capacity'] ?? 0);

        if ($duration <= 0 || $capacity <= 0 || $start < $earliest || ($end - $start) !== $duration) {
            throw new InvalidArgumentException('Sertifikat ZoneReservationCertificate tidak valid secara domain.');
        }

        /** @var list<array{start: int, end: int}> $staffBlocks */
        $staffBlocks = $this->canonicalInput['staff_blocks'] ?? [];
        foreach ($staffBlocks as $block) {
            if ($start < $block['end'] && $end > $block['start']) {
                throw new InvalidArgumentException('Sertifikat ZoneReservationCertificate melanggar blokir staf.');
            }
        }

        /** @var list<array{start: int, end: int}> $reservations */
        $reservations = $this->canonicalInput['reservations'] ?? [];
        for ($s = $start; $s < $end; $s++) {
            $active = 0;
            foreach ($reservations as $res) {
                if ($s >= $res['start'] && $s < $res['end']) {
                    $active++;
                }
            }
            if ($active >= $capacity) {
                throw new InvalidArgumentException("Sertifikat ZoneReservationCertificate melanggar kapasitas zona di menit {$s}.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $canonicalInput
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>  $result
     */
    private static function computeHash(array $canonicalInput, array $steps, array $result): string
    {
        $payload = self::canonicalize([
            'algorithm' => 'ZoneReservationScheduler:J1',
            'input' => $canonicalInput,
            'steps' => $steps,
            'result' => $result,
        ]);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $k => $v) {
            $value[$k] = self::canonicalize($v);
        }

        return $value;
    }
}
