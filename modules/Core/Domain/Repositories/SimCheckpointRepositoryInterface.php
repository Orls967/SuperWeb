<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\SimCheckpoint;

interface SimCheckpointRepositoryInterface
{
    public function findByRunAndDay(string $runId, int $day): ?SimCheckpoint;

    /**
     * @return Collection<int, SimCheckpoint>
     */
    public function getForRun(string $runId): Collection;

    public function getLatestForRun(string $runId): ?SimCheckpoint;

    public function save(SimCheckpoint $checkpoint): bool;

    public function deleteOlderThan(string $runId, int $day): int;
}
