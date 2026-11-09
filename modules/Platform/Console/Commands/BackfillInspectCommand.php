<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Backfill\BackfillThroughputTelemetry;
use Modules\Platform\Domain\Models\DataBackfill;

class BackfillInspectCommand extends Command
{
    protected $signature = 'platform:backfill-inspect {job_name : Nama pekerjaan backfill}';

    protected $description = 'Memeriksa progres, throughput dan estimasi selesai (ETA) pekerjaan backfill';

    public function handle(BackfillThroughputTelemetry $telemetry): int
    {
        $jobName = $this->argument('job_name');
        $backfill = DataBackfill::where('job_name', $jobName)->first();

        if ($backfill === null) {
            $this->error("Pekerjaan backfill '{$jobName}' tidak ditemukan.");

            return 1;
        }

        $elapsedMinutes = max(1.0, (float) now()->diffInMinutes($backfill->created_at));
        $data = $telemetry->calculateTelemetry($backfill, $elapsedMinutes);

        $this->info("Inspeksi Backfill: {$jobName}");
        $this->line("  - Tabel Target: {$backfill->target_table}");
        $this->line("  - Status: {$backfill->status}");
        $this->line("  - Progress: {$data['progress_pct']}% ({$backfill->cursor_pointer}/{$backfill->total_records})");
        $this->line("  - Throughput: {$data['current_tps']} TPS");
        $this->line("  - Sisa Baris: {$data['remaining_records']}");
        $this->line("  - Estimasi Selesai (ETA): {$data['eta_minutes']} menit");

        return 0;
    }
}
