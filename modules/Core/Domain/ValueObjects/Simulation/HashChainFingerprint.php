<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects\Simulation;

use InvalidArgumentException;

/**
 * Value object untuk representasi fingerprint hash SHA-256 rantai simulasi.
 */
final readonly class HashChainFingerprint
{
    public function __construct(
        public string $value,
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/i', $this->value)) {
            throw new InvalidArgumentException("Format hash chain SHA-256 tidak valid: {$this->value}");
        }
    }

    public static function fromHex(string $hash): self
    {
        return new self(strtolower(trim($hash)));
    }

    public static function genesis(SeedValue|int $seed): self
    {
        $rawSeed = $seed instanceof SeedValue ? $seed->value : $seed;
        $hash = hash('sha256', "sim-run|seed={$rawSeed}");

        return new self($hash);
    }

    public function next(int $dayNumber, array $daySummary): self
    {
        ksort($daySummary);
        $canonicalJson = json_encode($daySummary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payload = "{$this->value}|{$dayNumber}|{$canonicalJson}";
        $newHash = hash('sha256', $payload);

        return new self($newHash);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function toHex(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
