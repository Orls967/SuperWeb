<?php

declare(strict_types=1);

namespace Modules\Core\Application\Exporters;

use Modules\Core\Domain\Models\SimTwinState;

final class SimulationTwinCsvExporter
{
    /**
     * @param  resource  $handle
     */
    public function export($handle, ?string $entityType = null): void
    {
        fputcsv($handle, [
            'ID Log',
            'Tipe Entitas',
            'ID Entitas',
            'Event Hash',
            'Prev Hash',
            'Is Sandbox',
            'Payload JSON',
            'Dibuat Pada',
        ]);

        $query = SimTwinState::orderBy('id', 'asc');
        if ($entityType !== null) {
            $query->where('entity_type', $entityType);
        }

        $query->chunk(100, function ($twins) use ($handle) {
            foreach ($twins as $tw) {
                fputcsv($handle, [
                    $tw->id,
                    $tw->entity_type,
                    $tw->entity_id,
                    $tw->event_hash,
                    $tw->prev_hash ?? 'GENESIS',
                    $tw->is_sandbox ? '1' : '0',
                    json_encode($tw->state_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $tw->created_at?->toIso8601String() ?? '',
                ]);
            }
        });
    }
}
