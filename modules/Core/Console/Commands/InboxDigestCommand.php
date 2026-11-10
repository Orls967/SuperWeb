<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\TaskInbox\TaskDigestService;

class InboxDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inbox:digest {--user= : ID pengguna target}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghasilkan ringkasan beban kerja tugas aktif di Kotak Masuk';

    /**
     * Execute the console command.
     */
    public function handle(TaskDigestService $digestService): int
    {
        $userId = $this->option('user');
        $this->info(sprintf('Membuat digest tugas %s...', $userId ? "untuk pengguna [{$userId}]" : 'seluruh sistem'));

        $summary = $digestService->generateDigestSummary($userId);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Tugas Pending', $summary['pending_count']],
                ['Tugas Urgent', $summary['urgent_count']],
                ['Tugas Dieskalasi', $summary['escalated_count']],
                ['Tugas Melewati Batas Waktu (Overdue)', $summary['overdue_count']],
            ]
        );

        if (! empty($summary['by_module'])) {
            $this->info("\nDistribusi Tugas per Modul:");
            $modRows = [];
            foreach ($summary['by_module'] as $mod => $cnt) {
                $modRows[] = [$mod, $cnt];
            }
            $this->table(['Modul', 'Total Tugas'], $modRows);
        }

        return 0;
    }
}
