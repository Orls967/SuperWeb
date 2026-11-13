<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

use InvalidArgumentException;

/**
 * Sertifikat deterministik & verifier untuk pengelompokan wave FFD J2 (PR2).
 */
final readonly class WaveBinPackerCertificate
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
        return new self($canonicalInput, $steps, $result, self::computeHash($canonicalInput, $steps, $result));
    }

    public function verifyOrFail(): void
    {
        $expected = self::computeHash($this->canonicalInput, $this->steps, $this->result);
        if (! hash_equals($expected, $this->sha256)) {
            throw new InvalidArgumentException('Sertifikat WaveBinPackerCertificate rusak: hash SHA-256 tidak cocok.');
        }

        $capacity = (int) ($this->canonicalInput['capacity'] ?? 0);
        /** @var list<array{id: string|int, size: int, carrier: string, cutoff: int}> $orders */
        $orders = $this->canonicalInput['orders'] ?? [];
        /** @var list<array{wave_index: int, carrier: string, cutoff: int, status: string, load: int, items: list<array{id: string|int, size: int}>}> $waves */
        $waves = $this->result['waves'] ?? [];

        $seenIds = [];
        foreach ($waves as $wave) {
            $sum = 0;
            foreach ($wave['items'] as $item) {
                $key = (string) $item['id'];
                if (isset($seenIds[$key])) {
                    throw new InvalidArgumentException("Order {$key} muncul ganda di hasil wave.");
                }
                $seenIds[$key] = true;
                $sum += (int) $item['size'];
            }
            if ($sum !== (int) $wave['load']) {
                throw new InvalidArgumentException('Total beban wave tidak cocok dengan jumlah item.');
            }
            if ($wave['status'] !== 'OVERSIZE' && $sum > $capacity) {
                throw new InvalidArgumentException('Beban wave melebihi kapasitas tanpa status OVERSIZE.');
            }
        }

        if (count($seenIds) !== count($orders)) {
            throw new InvalidArgumentException('Jumlah order di wave tidak sama dengan jumlah order masukan.');
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
            'algorithm' => 'WaveBinPacker:J2',
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
