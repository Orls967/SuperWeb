<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements ArrayAccess<string, int>
 * @implements IteratorAggregate<string, int>
 */
final readonly class HamiltonApportionmentResult implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @param  array<string, int>  $allocations
     */
    public function __construct(
        public array $allocations,
        public int $unallocated,
        public HamiltonCertificate $certificate,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->allocations[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->allocations[$offset] ?? null;
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
        return count($this->allocations);
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->allocations);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->allocations;
    }
}
