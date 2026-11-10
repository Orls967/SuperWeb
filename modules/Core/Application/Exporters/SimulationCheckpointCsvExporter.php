<?php

declare(strict_types=1);

namespace Modules\Core\Application\Exporters;

use Modules\Core\Domain\Models\SimCheckpoint;

final class SimulationCheckpointCsvExporter
{
    /**
     * @param  resource  $handle
     */
    public function export($handle, ?string $runId = null): void
    {
        fputcsv($handle, [
            'ID Checkpoint',
            'ID Sesi Run',
            'Hari Ke',
            'Chain Hash SHA-256',
            'Jumlah Handler Tercatat',
            'Dibuat Pada',
        ]);

        $query = SimCheckpoint::orderBy('day_number', 'asc');
        if ($runId !== null) {
            $query->where('run_id', $runId);
        }

        $query->chunk(100, function ($checkpoints) use ($handle) {
            foreach ($checkpoints as $cp) {
                $handlerCount = is_array($cp->handler_hashes) ? count($cp->handler_hashes) : 0;
                fputcsv($handle, [
                    $cp->id,
                    $cp->run_id,
                    $cp->day_number,
                    $cp->chain_hash,
                    $handlerCount,
                    $cp->created_at?->toIso8601String() ?? '',
                ]);
            }
        });
    }
}
