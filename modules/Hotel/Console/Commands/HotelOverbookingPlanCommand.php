<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Domain\Algorithms\OverbookingLimitOptimizer;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Services\HotelExternalFactors;

final class HotelOverbookingPlanCommand extends Command
{
    protected $signature = 'hotel:overbooking-plan {date : Tanggal Bisnis Y-m-d}';

    protected $description = 'Menjalankan optimasi overbooking H1 harian per properti';

    public function handle(): int
    {
        $date = (string) $this->argument('date');
        $properties = HotelProperty::all();
        $noShowDist = HotelExternalFactors::getNoShowDistributionBps();

        foreach ($properties as $prop) {
            $opt = OverbookingLimitOptimizer::optimize(
                capacity: 100,
                noShowDistributionBps: $noShowDist,
                revenuePerRoomIdr: 1000000,
                walkCostIdr: 2500000,
                policyMaxPercentBps: 300
            );
            $this->line("Properti {$prop->name} pada {$date}: Optimal k = +{$opt['optimal_k']} kamar (E[Net] = Rp " . number_format($opt['expected_net_value_idr'], 0, ',', '.') . ")");
        }

        $this->info("Rencana optimasi overbooking selesai dihitung.");
        return self::SUCCESS;
    }
}
