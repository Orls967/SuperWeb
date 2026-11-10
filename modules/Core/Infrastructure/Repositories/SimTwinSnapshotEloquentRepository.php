<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\SimTwinSnapshot;
use Modules\Core\Domain\Repositories\SimTwinSnapshotRepositoryInterface;

final class SimTwinSnapshotEloquentRepository implements SimTwinSnapshotRepositoryInterface
{
    public function findSpecific(string $entityType, string $entityId, int $day): ?SimTwinSnapshot
    {
        return SimTwinSnapshot::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('day_number', $day)
            ->first();
    }

    public function getHistoryForEntity(string $entityType, string $entityId): Collection
    {
        return SimTwinSnapshot::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('day_number', 'asc')
            ->get();
    }

    public function save(SimTwinSnapshot $snapshot): bool
    {
        return $snapshot->save();
    }
}
