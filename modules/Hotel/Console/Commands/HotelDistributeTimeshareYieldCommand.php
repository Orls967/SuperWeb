<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\DistributeTimeshareRentalYieldAction;
use Modules\Hotel\Domain\Models\TimeshareUnit;

class HotelDistributeTimeshareYieldCommand extends Command
{
    protected $signature = 'hotel:timeshare-yield-distribute {--unit-id= : Unit Timeshare ID spesifik}';

    protected $description = 'Menjalankan kalkulasi dan distribusi bagi hasil sewa pool timeshare resort ke ledger investor';

    public function handle(DistributeTimeshareRentalYieldAction $action): int
    {
        $this->info('Memulai pemrosesan distribusi imbal hasil rental timeshare...');

        $unitId = $this->option('unit-id');
        $query = TimeshareUnit::query();

        if ($unitId) {
            $query->where('id', $unitId);
        }

        $units = $query->get();
        if ($units->isEmpty()) {
            $this->warn('Tidak ada unit timeshare ditemukan untuk diproses.');

            return self::SUCCESS;
        }

        $totalDistributed = 0;
        foreach ($units as $unit) {
            $rentalPoolGross = (int) ($unit->daily_rental_rate_idr * 30);
            if ($rentalPoolGross <= 0) {
                continue;
            }

            try {
                $cert = $action->execute((string) $unit->id, $rentalPoolGross, 1500);
                $this->line("Unit [{$unit->unit_code}]: Gross IDR {$rentalPoolGross} -> Net Dividend IDR {$cert->totalDividendDistributedIdr}");
                $totalDistributed += $cert->totalDividendDistributedIdr;
            } catch (\Throwable $e) {
                $this->error("Gagal mendistribusikan yield untuk unit {$unit->unit_code}: ".$e->getMessage());
            }
        }

        $this->info('Distribusi selesai. Total dividen terdistribusi: IDR '.number_format($totalDistributed));

        return self::SUCCESS;
    }
}
