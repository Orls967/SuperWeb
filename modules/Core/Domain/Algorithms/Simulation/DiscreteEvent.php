<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation;

/**
 * Value object untuk peristiwa diskrit dalam simulasi.
 */
class DiscreteEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $time,
        public readonly int $priority,
        public readonly string $name,
        public readonly int $seq,
        public readonly array $payload = [],
    ) {}

    /**
     * Membandingkan dua peristiwa untuk min-heap.
     * Mengembalikan < 0 jika $this harus dieksekusi lebih dulu daripada $other.
     */
    public function compareTo(DiscreteEvent $other): int
    {
        if ($this->time !== $other->time) {
            return $this->time <=> $other->time;
        }

        if ($this->priority !== $other->priority) {
            return $this->priority <=> $other->priority;
        }

        return $this->seq <=> $other->seq;
    }

    /**
     * @return array{time: int, priority: int, name: string, seq: int}
     */
    public function toArray(): array
    {
        return [
            'time' => $this->time,
            'priority' => $this->priority,
            'name' => $this->name,
            'seq' => $this->seq,
        ];
    }
}
