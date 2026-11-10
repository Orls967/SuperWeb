<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\TaskPerformanceMetricService;

class TaskSlaAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:sla-audit {--days=30 : Rentang hari analisis audit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan audit performa dan rasio kepatuhan SLA Kotak Masuk Tugas Saya';

    /**
     * Execute the console command.
     */
    public function handle(TaskPerformanceMetricService $metricService): int
    {
        $days = (int) $this->option('days');
        $this->info("Menjalankan Audit Kepatuhan SLA Kotak Masuk ({$days} hari terakhir)...");

        $metrics = $metricService->computeMetrics($days);

        $this->table(
            ['Metrik Performa SLA', 'Nilai'],
            [
                ['Total Tugas', $metrics['total_tasks']],
                ['Tugas Selesai', $metrics['completed_tasks']],
                ['Tingkat Penyelesaian (%)', $metrics['completion_rate_pct'].'%'],
                ['Jumlah Pelanggaran SLA', $metrics['sla_breached_count']],
                ['Tingkat Kepatuhan SLA (%)', $metrics['sla_compliance_rate_pct'].'%'],
                ['Waktu Tanggap Median (Jam)', $metrics['median_turnaround_hours'].' jam'],
            ]
        );

        if ($metrics['sla_compliance_rate_pct'] < 80.0) {
            $this->error('Pelanggaran SLA terdeteksi: rasio kepatuhan di bawah standar 80%.');

            return 1;
        }

        $this->info('Audit SLA lulus: tingkat kepatuhan memenuhi standar operasional.');

        return 0;
    }
}
