<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use InvalidArgumentException;
use Modules\Core\Domain\Algorithms\Simulation\DiscreteEventScheduler;
use Modules\Core\Domain\Algorithms\Simulation\ValueObjects\DiscreteEvent;

/**
 * Mesin pengatur kemajuan waktu virtual berbutir halus (fine-grained virtual time progression).
 * Mengintegrasikan DiscreteEventScheduler A1 dengan interval waktu bisnis.
 */
final class VirtualTimeProgressionEngine
{
    private int $currentVirtualSecond = 0;

    private readonly DiscreteEventScheduler $scheduler;

    public function __construct(
        int $initialVirtualSecond = 0,
        ?DiscreteEventScheduler $scheduler = null,
    ) {
        if ($initialVirtualSecond < 0) {
            throw new InvalidArgumentException("Waktu virtual awal tidak boleh negatif: {$initialVirtualSecond}");
        }
        $this->currentVirtualSecond = $initialVirtualSecond;
        $this->scheduler = $scheduler ?? new DiscreteEventScheduler;
    }

    public function getCurrentVirtualSecond(): int
    {
        return $this->currentVirtualSecond;
    }

    public function getCurrentDay(): int
    {
        return intdiv($this->currentVirtualSecond, 86400) + 1;
    }

    public function scheduleEvent(string $id, int $targetSecond, int $priority = 10, int $sequence = 0): void
    {
        $this->scheduler->schedule(new DiscreteEvent(
            id: $id,
            virtualSecond: $targetSecond,
            priority: $priority,
            sequence: $sequence,
        ), $this->currentVirtualSecond);
    }

    /**
     * Memajukan waktu virtual hingga detik tertentu dan mengembalikan semua event yang jatuh tempo.
     *
     * @return array<int, DiscreteEvent>
     */
    public function advanceToSecond(int $targetSecond): array
    {
        if ($targetSecond < $this->currentVirtualSecond) {
            throw new InvalidArgumentException("Waktu virtual tidak dapat mundur: target={$targetSecond}, current={$this->currentVirtualSecond}");
        }

        $triggered = [];

        while (! $this->scheduler->isEmpty()) {
            $next = $this->scheduler->peekNext();
            if ($next === null || $next->virtualSecond > $targetSecond) {
                break;
            }

            $ev = $this->scheduler->popNext();
            if ($ev !== null) {
                $triggered[] = $ev;
            }
        }

        $this->currentVirtualSecond = $targetSecond;

        return $triggered;
    }

    /**
     * Memajukan waktu sebanyak N hari virtual.
     *
     * @return array<int, DiscreteEvent>
     */
    public function advanceDays(int $days): array
    {
        if ($days <= 0) {
            throw new InvalidArgumentException("Penambahan hari harus positif: {$days}");
        }

        $targetSecond = $this->currentVirtualSecond + ($days * 86400);

        return $this->advanceToSecond($targetSecond);
    }
}
