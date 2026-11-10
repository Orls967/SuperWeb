<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\Simulation;

use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\ValueObjects\Simulation\DivergencePoint;

final class DiffSimulationRunsAction
{
    public function execute(SimRun $runA, SimRun $runB): DivergencePoint
    {
        if ($runA->seed !== $runB->seed) {
            return DivergencePoint::diverged(
                day: 1,
                reason: "Seed berbeda sejak awal (Run A: {$runA->seed}, Run B: {$runB->seed})",
                hashA: (string) $runA->chain_hash,
                hashB: (string) $runB->chain_hash,
            );
        }

        $checkpointsA = SimCheckpoint::where('run_id', $runA->id)
            ->orderBy('day_number', 'asc')
            ->get()
            ->keyBy('day_number');

        $checkpointsB = SimCheckpoint::where('run_id', $runB->id)
            ->orderBy('day_number', 'asc')
            ->get()
            ->keyBy('day_number');

        $commonDays = $checkpointsA->keys()->intersect($checkpointsB->keys())->sort()->values();

        foreach ($commonDays as $day) {
            /** @var SimCheckpoint $cpA */
            $cpA = $checkpointsA[$day];
            /** @var SimCheckpoint $cpB */
            $cpB = $checkpointsB[$day];

            if ($cpA->chain_hash !== $cpB->chain_hash) {
                $handlerA = (array) ($cpA->handler_hashes ?? []);
                $handlerB = (array) ($cpB->handler_hashes ?? []);
                $allHandlers = array_unique(array_merge(array_keys($handlerA), array_keys($handlerB)));

                $diffs = [];
                foreach ($allHandlers as $h) {
                    $valA = $handlerA[$h] ?? null;
                    $valB = $handlerB[$h] ?? null;
                    $diffs[$h] = [
                        'a' => $valA,
                        'b' => $valB,
                        'identical' => $valA === $valB,
                    ];
                }

                return DivergencePoint::diverged(
                    day: (int) $day,
                    reason: "Divergensi hash rantai pada hari ke-{$day}",
                    hashA: $cpA->chain_hash,
                    hashB: $cpB->chain_hash,
                    discrepancies: $diffs,
                );
            }
        }

        if ($runA->chain_hash !== $runB->chain_hash) {
            $minDays = min($runA->current_day, $runB->current_day);

            return DivergencePoint::diverged(
                day: max(1, $minDays),
                reason: 'Hash akhir sesi berbeda meskipun checkpoint awal identik',
                hashA: (string) $runA->chain_hash,
                hashB: (string) $runB->chain_hash,
            );
        }

        return DivergencePoint::match();
    }
}
