<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Application\Services\AviationYieldManagementService;

class AviationYieldRebalanceCommand extends Command
{
    protected $signature = 'avi:yield-rebalance
                            {flight_id? : ID penerbangan spesifik (opsional)}
                            {--days=7 : Rentang hari ke depan untuk pemrosesan massal}';

    protected $description = 'Hitung ulang dan optimalkan batas pemesanan fare bucket EMSR-b';

    public function handle(AviationYieldManagementService $yieldService): int
    {
        $flightId = $this->argument('flight_id');

        if ($flightId !== null) {
            $this->info("Mengoptimalkan yield untuk penerbangan [ID: {$flightId}]...");
            $res = $yieldService->rebalanceFlightBuckets((int) $flightId);
            $this->info("Berhasil memperbarui [{$res['buckets_created_or_updated']}] bucket. Hash: {$res['certificate_hash']}");

            return 0;
        }

        $days = (int) $this->option('days');
        $this->info("Menjalankan optimasi yield massal untuk seluruh penerbangan {$days} hari ke depan...");
        $processed = $yieldService->batchRebalanceUpcomingFlights($days);
        $this->info("Selesai mengoptimalkan [{$processed}] penerbangan.");

        return 0;
    }
}
