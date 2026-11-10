<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\TaskInbox\TaskEscalationService;

class InboxEscalateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inbox:escalate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengecek dan mengeksekusi eskalasi terhadap tugas yang melewati tenggat waktu';

    /**
     * Execute the console command.
     */
    public function handle(TaskEscalationService $escalationService): int
    {
        $this->info('Memindai tugas yang melewati tenggat waktu (overdue)...');

        $escalatedCount = $escalationService->escalateOverdueTasks();

        $this->info(sprintf('Proses eskalasi selesai. Sebanyak %d tugas telah berhasil dieskalasi.', $escalatedCount));

        return 0;
    }
}
