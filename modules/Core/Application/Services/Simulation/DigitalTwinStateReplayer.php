<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimTwinSnapshot;
use Modules\Core\Domain\Models\SimTwinState;

/**
 * Mesin reka ulang (time-travel state replayer) Digital Twin.
 * Merekonstruksi keadaan entitas pada waktu lampau tertentu berdasarkan snapshot dan riwayat event hash.
 */
final class DigitalTwinStateReplayer
{
    /**
     * @return array{
     *     entity_type: string,
     *     entity_id: string,
     *     target_day: int,
     *     reconstructed_state: array<string, mixed>,
     *     base_snapshot_day: int|null,
     *     events_applied_count: int,
     *     reconstruction_hash: string
     * }
     */
    public function reconstructStateAtDay(string $entityType, string $entityId, int $targetDay): array
    {
        // 1. Cari snapshot terdekat sebelum atau sama dengan targetDay
        $baseSnapshot = SimTwinSnapshot::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('day_number', '<=', $targetDay)
            ->orderBy('day_number', 'desc')
            ->first();

        $currentState = $baseSnapshot ? (array) $baseSnapshot->state_payload : [];
        $baseDay = $baseSnapshot?->day_number;

        // 2. Ambil semua event state yang terjadi setelah snapshot hingga targetDay
        $query = SimTwinState::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id', 'asc');

        if ($baseSnapshot) {
            $query->where('created_at', '>=', $baseSnapshot->created_at);
        }

        $events = $query->get();
        $applied = 0;

        foreach ($events as $ev) {
            $payload = (array) $ev->state_payload;
            $currentState = array_merge($currentState, $payload);
            $applied++;
        }

        ksort($currentState);
        $canonical = json_encode($currentState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $reconHash = hash('sha256', "reconstructed|{$entityType}|{$entityId}|{$targetDay}|{$canonical}");

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'target_day' => $targetDay,
            'reconstructed_state' => $currentState,
            'base_snapshot_day' => $baseDay,
            'events_applied_count' => $applied,
            'reconstruction_hash' => $reconHash,
        ];
    }
}
