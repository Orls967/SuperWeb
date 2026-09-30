<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Mall\Application\Actions\ExpirePointsAction;

class ExpirePointsCommand extends Command
{
    protected $signature = 'mall:expire-points {--date= : Tanggal cut-off (YYYY-MM-DD)}';

    protected $description = 'Kedaluwarsakan Duta Points loyalitas yang telah melewati masa aktif (FIFO)';

    public function handle(ExpirePointsAction $action): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::now();

        $this->info("Menjalankan proses kedaluwarsa Duta Points per tanggal {$date->toDateString()}...");

        $expired = $action->execute($date);

        $this->info("✓ Sukses: {$expired} PTS telah dikedaluwarsakan dan dibukukan ke double-entry ledger.");

        return self::SUCCESS;
    }
}
