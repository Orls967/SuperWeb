<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mall\Application\Actions\ApplyLatePenaltiesAction;

class ApplyPenaltiesCommand extends Command
{
    protected $signature = 'mall:apply-penalties';

    protected $description = 'Hitung dan terapkan denda harian (0.1%/hari) pada invoice mall yang terlambat dan suspend lease > 30 hari';

    public function handle(ApplyLatePenaltiesAction $action): int
    {
        $this->info('Memeriksa invoice mall yang melewati jatuh tempo...');

        $result = $action->execute();

        $this->info('Penerapan denda selesai.');
        $this->line("- Invoice dikenakan denda: <comment>{$result['penalized_invoices']}</comment>");
        $this->line("- Kontrak lease disuspend (H+30): <comment>{$result['suspended_leases']}</comment>");

        return self::SUCCESS;
    }
}
