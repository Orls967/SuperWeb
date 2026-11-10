<?php

declare(strict_types=1);

namespace Modules\Core\Application\Exporters;

use Modules\Core\Domain\Models\SimRun;

final class SimulationRunCsvExporter
{
    /**
     * @param  resource  $handle
     */
    public function export($handle): void
    {
        fputcsv($handle, [
            'ID Sesi',
            'Nama Sesi',
            'Status',
            'Mode',
            'Seed',
            'Hari Saat Ini',
            'Total Hari',
            'Fingerprint Rantai SHA-256',
            'Dibuat Pada',
            'Selesai Pada',
        ]);

        SimRun::orderBy('created_at', 'desc')->chunk(100, function ($runs) use ($handle) {
            foreach ($runs as $r) {
                fputcsv($handle, [
                    $r->id,
                    $r->name,
                    $r->status,
                    $r->mode,
                    $r->seed,
                    $r->current_day,
                    $r->total_days,
                    $r->chain_hash,
                    $r->created_at?->toIso8601String() ?? '',
                    $r->completed_at?->toIso8601String() ?? '',
                ]);
            }
        });
    }
}
