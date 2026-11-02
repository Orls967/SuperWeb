<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use ArrayAccess;
use Brick\Math\BigRational;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements ArrayAccess<string, int>
 * @implements IteratorAggregate<string, int>
 */
final readonly class DagExplosionResult implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @param  array<string, BigRational>  $rawRationalTotals
     * @param  array<string, int>  $rawCeiledTotals
     */
    public function __construct(
        public array $rawRationalTotals,
        public array $rawCeiledTotals,
        public DagExplosionCertificate $certificate,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->rawCeiledTotals[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->rawCeiledTotals[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Immutable
    }

    public function offsetUnset(mixed $offset): void
    {
        // Immutable
    }

    public function count(): int
    {
        return count($this->rawCeiledTotals);
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->rawCeiledTotals);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->rawCeiledTotals;
    }
}
