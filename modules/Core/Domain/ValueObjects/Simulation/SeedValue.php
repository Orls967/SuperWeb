<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects\Simulation;

use InvalidArgumentException;

/**
 * Value object untuk nilai seed RNG deterministik MINSTD (1 .. 2^31 - 2).
 */
final readonly class SeedValue
{
    public const MIN_SEED = 1;

    public const MAX_SEED = 2147483646; // 2^31 - 2

    public function __construct(
        public int $value,
    ) {
        if ($this->value < self::MIN_SEED || $this->value > self::MAX_SEED) {
            throw new InvalidArgumentException(
                'Nilai seed harus berada di dalam interval ['.self::MIN_SEED.', '.self::MAX_SEED."]: {$this->value}"
            );
        }
    }

    public static function fromInt(int $val): self
    {
        return new self($val);
    }

    /**
     * Menghasilkan turunan seed untuk nama sub-stream.
     */
    public function deriveForStream(string $streamName): self
    {
        $hash = hash('sha256', "stream:{$this->value}:{$streamName}");
        $derived = (hexdec(substr($hash, 0, 8)) % (self::MAX_SEED - 1)) + 1;

        return new self($derived);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
