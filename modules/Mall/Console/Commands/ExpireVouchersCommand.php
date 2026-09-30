<?php

declare(strict_types=1);

namespace Modules\Mall\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Mall\Application\Actions\ExpireVouchersAction;

class ExpireVouchersCommand extends Command
{
    protected $signature = 'mall:expire-vouchers {--date= : Tanggal cut-off (YYYY-MM-DD)}';

    protected $description = 'Kedaluwarsakan voucher mall aktif yang telah melewati masa berlaku dan bukukan sebagai pendapatan breakage';

    public function handle(ExpireVouchersAction $action): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::now();

        $this->info("Menjalankan proses kedaluwarsa voucher per tanggal {$date->toDateString()}...");

        $count = $action->execute($date);

        $this->info("✓ Sukses: {$count} voucher kedaluwarsa telah dialihkan ke pendapatan breakage.");

        return self::SUCCESS;
    }
}
