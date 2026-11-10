<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Modules\Core\Domain\Models\SimCheckpoint;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Models\SimSeederCheckpoint;
use Modules\Core\Domain\Models\SimTwinState;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SimulationExportController extends Controller
{
    use AuthorizesRequests;

    public function export(Request $request, string $type): StreamedResponse
    {
        $this->authorize('viewAny', SimRun::class);

        $filename = "sim_{$type}_export_".now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($type, $request): void {
            $handle = fopen('php://output', 'w');

            match ($type) {
                'runs' => $this->exportRuns($handle),
                'checkpoints' => $this->exportCheckpoints($handle, (string) $request->query('run_id')),
                'twins' => $this->exportTwins($handle),
                'scale' => $this->exportScaleBenchmarks($handle),
                default => fputcsv($handle, ['Error', 'Tipe ekspor tidak dikenal']),
            };

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * @param  resource  $handle
     */
    protected function exportRuns($handle): void
    {
        fputcsv($handle, ['ID', 'Nama', 'Status', 'Mode', 'Seed', 'Hari Saat Ini', 'Total Hari', 'Chain Hash', 'Dibuat']);
        SimRun::orderBy('created_at', 'desc')->chunk(100, function ($runs) use ($handle): void {
            foreach ($runs as $r) {
                fputcsv($handle, [
                    $r->id,
                    $r->name,
                    $r->status,
                    $r->mode,
                    $r->seed,
                    $r->current_day,
                    $r->total_days,
                    $r->chain_hash ?? 'N/A',
                    $r->created_at?->toIso8601String() ?? '',
                ]);
            }
        });
    }

    /**
     * @param  resource  $handle
     */
    protected function exportCheckpoints($handle, string $runId): void
    {
        fputcsv($handle, ['Run ID', 'Nomor Hari', 'Chain Hash', 'Waktu']);
        $query = SimCheckpoint::orderBy('day_number', 'asc');
        if ($runId !== '') {
            $query->where('run_id', $runId);
        }

        $query->chunk(100, function ($cps) use ($handle): void {
            foreach ($cps as $cp) {
                fputcsv($handle, [
                    $cp->run_id,
                    $cp->day_number,
                    $cp->chain_hash,
                    $cp->created_at?->toIso8601String() ?? '',
                ]);
            }
        });
    }

    /**
     * @param  resource  $handle
     */
    protected function exportTwins($handle): void
    {
        fputcsv($handle, ['ID', 'Tipe Entitas', 'ID Entitas', 'State Hash', 'Prev Hash', 'Sandbox', 'Valid From']);
        SimTwinState::orderBy('id', 'desc')->chunk(100, function ($twins) use ($handle): void {
            foreach ($twins as $t) {
                fputcsv($handle, [
                    $t->id,
                    $t->entity_type,
                    $t->entity_id,
                    $t->state_hash,
                    $t->prev_hash ?? 'GENESIS',
                    $t->is_sandbox ? '1' : '0',
                    $t->valid_from?->toIso8601String() ?? '',
                ]);
            }
        });
    }

    /**
     * @param  resource  $handle
     */
    protected function exportScaleBenchmarks($handle): void
    {
        fputcsv($handle, ['Seeder', 'Stage', 'Tier', 'Total Injeksi', 'Throughput RPS', 'Status']);
        SimSeederCheckpoint::all()->each(function ($cp) use ($handle): void {
            fputcsv($handle, [
                $cp->seeder_name,
                $cp->stage,
                $cp->metrics['tier'] ?? 'T1',
                $cp->last_processed_id,
                $cp->metrics['throughput_rps'] ?? 'N/A',
                $cp->completed ? 'Selesai' : 'In-Progress',
            ]);
        });
    }
}
