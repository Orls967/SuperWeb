<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Illuminate\Support\Collection;
use Modules\Core\Application\Actions\Simulation\TakeTwinSnapshotAction;
use Modules\Core\Domain\Models\SimTwinSnapshot;
use Modules\Core\Domain\Models\SimTwinState;

/**
 * Layanan arsip, kompresi delta, dan verifikasi snapshot Digital Twin.
 * Mengontrol persistensi periodik keadaan entitas bayangan dan kalkulasi delta kompresi.
 */
final class TwinSnapshotArchiveService
{
    public function __construct(
        private readonly TakeTwinSnapshotAction $snapshotAction,
    ) {}

    /**
     * Mengambil snapshot dari semua entitas aktif yang memiliki rekaman twin state.
     *
     * @return Collection<int, SimTwinSnapshot>
     */
    public function snapshotAllActiveEntities(int $dayNumber, ?string $runId = null): Collection
    {
        $distinctEntities = SimTwinState::select('entity_type', 'entity_id')
            ->distinct()
            ->get();

        $snapshots = collect();

        foreach ($distinctEntities as $entity) {
            $latest = SimTwinState::where('entity_type', $entity->entity_type)
                ->where('entity_id', $entity->entity_id)
                ->orderBy('id', 'desc')
                ->first();

            if ($latest) {
                $snap = $this->snapshotAction->execute(
                    type: $entity->entity_type,
                    id: $entity->entity_id,
                    day: $dayNumber,
                    payload: (array) $latest->state_payload,
                    runId: $runId,
                );
                $snapshots->push($snap);
            }
        }

        return $snapshots;
    }

    /**
     * Menghitung delta perubahan antara snapshot hari ini dan snapshot sebelumnya.
     *
     * @return array{
     *     entity_type: string,
     *     entity_id: string,
     *     prev_day: int|null,
     *     current_day: int,
     *     added_keys: array<string, mixed>,
     *     removed_keys: array<string, mixed>,
     *     modified_keys: array<string, array{from: mixed, to: mixed}>,
     *     identical: bool
     * }
     */
    public function computeDelta(string $entityType, string $entityId, int $currentDay): array
    {
        $currentSnap = SimTwinSnapshot::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('day_number', $currentDay)
            ->first();

        $prevSnap = SimTwinSnapshot::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('day_number', '<', $currentDay)
            ->orderBy('day_number', 'desc')
            ->first();

        $payloadCurrent = $currentSnap ? (array) $currentSnap->state_payload : [];
        $payloadPrev = $prevSnap ? (array) $prevSnap->state_payload : [];

        $added = [];
        $removed = [];
        $modified = [];

        foreach ($payloadCurrent as $k => $v) {
            if (! array_key_exists($k, $payloadPrev)) {
                $added[$k] = $v;
            } elseif ($payloadPrev[$k] !== $v) {
                $modified[$k] = [
                    'from' => $payloadPrev[$k],
                    'to' => $v,
                ];
            }
        }

        foreach ($payloadPrev as $k => $v) {
            if (! array_key_exists($k, $payloadCurrent)) {
                $removed[$k] = $v;
            }
        }

        $identical = empty($added) && empty($removed) && empty($modified);

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'prev_day' => $prevSnap?->day_number,
            'current_day' => $currentDay,
            'added_keys' => $added,
            'removed_keys' => $removed,
            'modified_keys' => $modified,
            'identical' => $identical,
        ];
    }
}
