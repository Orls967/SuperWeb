<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Certificates;

use InvalidArgumentException;

/**
 * Sertifikat deterministik & verifier untuk deteksi dan resolusi kebuntuan lalu lintas (X279-3, PR2).
 */
final readonly class TrafficDeadlockCertificate
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
            throw new InvalidArgumentException('Sertifikat TrafficDeadlockCertificate rusak: hash SHA-256 tidak cocok.');
        }

        $hasDeadlock = (bool) ($this->result['has_deadlock'] ?? false);
        /** @var list<string> $cycleNodes */
        $cycleNodes = $this->result['cycle_nodes'] ?? [];
        $victim = $this->result['victim_node'] ?? null;

        if ($hasDeadlock && (count($cycleNodes) < 2 && $victim === null)) {
            throw new InvalidArgumentException('Sertifikat TrafficDeadlockCertificate tidak konsisten: siklus kosong.');
        }

        if ($hasDeadlock && ! in_array($victim, $cycleNodes, true)) {
            throw new InvalidArgumentException('Sertifikat TrafficDeadlockCertificate: korban resolusi tidak berada di siklus.');
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
            'algorithm' => 'TrafficDeadlockDetector:X279-3',
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
