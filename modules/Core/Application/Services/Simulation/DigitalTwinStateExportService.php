<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services\Simulation;

use Modules\Core\Domain\Models\SimTwinSnapshot;
use Modules\Core\Domain\Models\SimTwinState;

/**
 * Layanan ekspor data Digital Twin lengkap dalam format JSON forensik.
 */
final class DigitalTwinStateExportService
{
    /**
     * @return array<string, mixed>
     */
    public function exportEntityDna(string $entityType, string $entityId): array
    {
        $states = SimTwinState::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('id', 'asc')
            ->get();

        $snapshots = SimTwinSnapshot::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('day_number', 'asc')
            ->get();

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'exported_at' => now()->toIso8601String(),
            'total_states' => $states->count(),
            'total_snapshots' => $snapshots->count(),
            'states' => $states->map(fn ($s) => [
                'id' => $s->id,
                'event_hash' => $s->event_hash,
                'prev_hash' => $s->prev_hash,
                'is_sandbox' => $s->is_sandbox,
                'payload' => $s->state_payload,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all(),
            'snapshots' => $snapshots->map(fn ($snap) => [
                'day_number' => $snap->day_number,
                'state_hash' => $snap->state_hash,
                'payload' => $snap->state_payload,
                'created_at' => $snap->created_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
