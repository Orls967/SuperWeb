<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

use InvalidArgumentException;

/**
 * Sertifikat deterministik & verifier untuk perhitungan NPV & payback rasional J3 (PR2).
 */
final readonly class AutomationNpvCertificate
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
            throw new InvalidArgumentException('Sertifikat AutomationNpvCertificate rusak: hash SHA-256 tidak cocok.');
        }

        if ((int) ($this->canonicalInput['rate_num'] ?? 0) <= 0 || (int) ($this->canonicalInput['rate_den'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Sertifikat AutomationNpvCertificate memuat suku bunga tidak valid.');
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
            'algorithm' => 'AutomationNpvCalculator:J3',
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
