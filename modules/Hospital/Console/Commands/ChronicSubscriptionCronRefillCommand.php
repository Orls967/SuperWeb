<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Application\Actions\Telemedicine\ExecuteBatchRefillRunAction;

class ChronicSubscriptionCronRefillCommand extends Command
{
    protected $signature = 'hsp:chronic-refill-cron {--days=3 : Jumlah hari peringatan dini sebelum obat habis}';

    protected $description = 'Menjalankan proses batch pengisian ulang otomatis bulanan untuk langganan obat kronis yang jatuh tempo';

    public function handle(ExecuteBatchRefillRunAction $refillAction): int
    {
        $days = (int) $this->option('days');
        $this->info("Menjalankan cron pengisian ulang obat kronis (ambang batas: {$days} hari ke depan)...");

        $result = $refillAction->execute($days);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Jumlah Order Dibuat', $result['processed_count']],
            ]
        );

        if ($result['processed_count'] > 0) {
            $this->info("Berhasil membuat {$result['processed_count']} pesanan refill otomatis.");
            foreach ($result['orders_created'] as $ord) {
                $this->line(" - [{$ord['subscription_code']}] Order: {$ord['order_code']} ({$ord['drug_name']}) -> Next Refill: {$ord['next_refill_date']}");
            }
        } else {
            $this->line('Tidak ada langganan obat kronis yang jatuh tempo saat ini.');
        }

        return self::SUCCESS;
    }
}
