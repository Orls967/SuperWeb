<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

final class ChangeWindowOverlapResolver
{
    /**
     * Resolves simultaneous changes targeting overlapping tables by sequencing them with protective buffers.
     *
     * @param  list<array{id: int, table: string, estimated_duration_minutes: int}>  $changes
     * @return list<array{change_id: int, sequence_order: int, delay_offset_minutes: int}>
     */
    public function resolveScheduleSequence(array $changes, int $bufferMinutes = 15): array
    {
        $schedule = [];
        $currentOffset = 0;
        $order = 1;

        foreach ($changes as $c) {
            $schedule[] = [
                'change_id' => $c['id'] ?? 0,
                'sequence_order' => $order++,
                'delay_offset_minutes' => $currentOffset,
            ];

            $duration = (int) ($c['estimated_duration_minutes'] ?? 30);
            $currentOffset += ($duration + $bufferMinutes);
        }

        return $schedule;
    }
}
