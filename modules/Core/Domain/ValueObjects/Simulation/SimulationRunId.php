<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ValueObjects\Simulation;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Value object untuk identifier unik sesi simulasi (UUID v4 atau string terformat).
 */
final readonly class SimulationRunId
{
    public function __construct(
        public string $value,
    ) {
        $trimmed = trim($this->value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('ID sesi simulasi tidak boleh kosong.');
        }
    }

    public static function generate(): self
    {
        return new self((string) Str::uuid());
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
