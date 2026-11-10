<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\SimTwinSnapshot;

interface SimTwinSnapshotRepositoryInterface
{
    public function findSpecific(string $entityType, string $entityId, int $day): ?SimTwinSnapshot;

    /**
     * @return Collection<int, SimTwinSnapshot>
     */
    public function getHistoryForEntity(string $entityType, string $entityId): Collection;

    public function save(SimTwinSnapshot $snapshot): bool;
}
