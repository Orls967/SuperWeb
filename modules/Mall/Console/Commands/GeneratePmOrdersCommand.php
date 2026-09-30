<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Mall\Application\Actions\GeneratePmWorkOrdersAction;

class GeneratePmOrdersCommand extends Command
{
    protected $signature = 'mall:generate-pm-orders {--date= : Tanggal evaluasi jatuh tempo (YYYY-MM-DD)}';

    protected $description = 'Generate Work Order Preventive Maintenance untuk seluruh aset mall yang jatuh tempo servis';

    public function handle(GeneratePmWorkOrdersAction $action): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $this->info("Memeriksa aset mall yang jatuh tempo perawatan preventif per tanggal {$date->toDateString()}...");

        $orders = $action->execute($date);

        $this->info("✓ Sukses: {$orders->count()} Work Order pemeliharaan preventif berhasil diterbitkan.");

        return self::SUCCESS;
    }
}
