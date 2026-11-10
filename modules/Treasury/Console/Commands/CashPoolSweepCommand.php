<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Application\Actions\ExecuteNotionalCashPoolSweepAction;
use Modules\Treasury\Domain\Models\CashPool;

class CashPoolSweepCommand extends Command
{
    protected $signature = 'treasury:pool-sweep {--pool= : Nama cash pool yang disapu}';

    protected $description = 'Jalankan penyapuan zero-balancing/target-balance cash pool holding & entitas anak';

    public function handle(ExecuteNotionalCashPoolSweepAction $action): int
    {
        $poolName = $this->option('pool');
        $query = CashPool::query();
        if ($poolName) {
            $query->where('pool_name', $poolName);
        }

        $pools = $query->get();
        if ($pools->isEmpty()) {
            $this->info('Tidak ada konfigurasi cash pool ditemukan.');

            return self::SUCCESS;
        }

        foreach ($pools as $p) {
            $this->info("Menjalankan sweep untuk pool: {$p->pool_name}...");
            $res = $action->execute($p);

            $this->line(' Nominal disapu: Rp '.number_format($res['swept_amount_idr']));
            $this->line(" Arah          : {$res['sweep_direction']}");
        }

        $this->info('Selesai menjalankan sweep pooling kas.');

        return self::SUCCESS;
    }
}
