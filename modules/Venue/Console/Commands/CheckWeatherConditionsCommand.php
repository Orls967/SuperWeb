<?php

declare(strict_types=1);

namespace Modules\Venue\Console\Commands;

use Illuminate\Console\Command;
use Modules\Venue\Application\Actions\AdjustZoneCapacityForWeatherAction;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Services\VenueExternalFactors;

class CheckWeatherConditionsCommand extends Command
{
    protected $signature = 'venue:weather-check';

    protected $description = 'Memeriksa faktor cuaca eksternal dan menyesuaikan kapasitas zona terbuka (Fase 89)';

    public function handle(
        VenueExternalFactors $factors,
        AdjustZoneCapacityForWeatherAction $adjustAction
    ): int {
        $rainProbBps = $factors->getRainProbabilityBps();
        $this->info("Probabilitas hujan saat ini: {$rainProbBps} bps");

        $openAirZones = EntertainmentZone::where('name', 'like', '%Outdoor%')
            ->orWhere('name', 'like', '%Pool%')
            ->orWhere('name', 'like', '%Sunset%')
            ->get();

        $adjustedCount = 0;
        foreach ($openAirZones as $zone) {
            $result = $adjustAction->execute(
                zone: $zone,
                rainProbabilityBps: $rainProbBps,
                windSpeedKmh: 20
            );

            if ($result['reduced']) {
                $adjustedCount++;
                $this->line("Zona {$zone->name} disesuaikan: {$result['adjusted_capacity']} kapasitas ({$result['reduction_reason']})");
            }
        }

        $this->info("Penyesuaian cuaca selesai. {$adjustedCount} zona terbuka disesuaikan.");

        return self::SUCCESS;
    }
}
