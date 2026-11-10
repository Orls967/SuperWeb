<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Repositories\SimCheckpointRepositoryInterface;

final class SimCheckpointEloquentRepository implements SimCheckpointRepositoryInterface
{
    public function findByRunAndDay(string $runId, int $day): ?SimCheckpoint
    {
        return SimCheckpoint::where('run_id', $runId)->where('day_number', $day)->first();
    }

    public function getForRun(string $runId): Collection
    {
        return SimCheckpoint::where('run_id', $runId)->orderBy('day_number', 'asc')->get();
    }

    public function getLatestForRun(string $runId): ?SimCheckpoint
    {
        return SimCheckpoint::where('run_id', $runId)->orderBy('day_number', 'desc')->first();
    }

    public function save(SimCheckpoint $checkpoint): bool
    {
        return $checkpoint->save();
    }

    public function deleteOlderThan(string $runId, int $day): int
    {
        return SimCheckpoint::where('run_id', $runId)->where('day_number', '<', $day)->delete();
    }
}
