<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\TaskInbox\TaskEscalationService;
use Modules\Core\Application\Actions\TaskInbox\TaskReminderScheduler;

class TaskSlaEscalateDaemonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:escalate-daemon {--dry-run : Simulasi pemindaian tanpa mengubah status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan daemon pemindaian eskalasi SLA dan pengiriman reminder tugas mendekati tenggat';

    /**
     * Execute the console command.
     */
    public function handle(TaskEscalationService $escalationService, TaskReminderScheduler $reminderScheduler): int
    {
        $this->info('Menjalankan Siklus Pemeriksaan SLA Daemon...');

        $reminders = $reminderScheduler->getPendingReminders(12);
        $this->info(sprintf('Ditemukan %d tugas yang membutuhkan reminder dalam 12 jam ke depan.', count($reminders)));

        if ($this->option('dry-run')) {
            $this->comment('Mode simulasi (dry-run) aktif. Tidak ada mutasi status yang dieksekusi.');

            return 0;
        }

        $escalated = $escalationService->escalateOverdueTasks();
        $this->info(sprintf('Eskalasi selesai. Sebanyak %d tugas overdue telah dinaikkan tingkat eskalasinya.', $escalated));

        return 0;
    }
}
