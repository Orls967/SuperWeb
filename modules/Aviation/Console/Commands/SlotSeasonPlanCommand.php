<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Application\Actions\AllocateSeasonalSlotsAction;
use Modules\Aviation\Domain\Models\AviationAirport;

class SlotSeasonPlanCommand extends Command
{
    protected $signature = 'aviation:slot-season-plan {season : Kode musim slot, mis. W26 atau S27}';

    protected $description = 'Menjalankan optimasi dan alokasi slot musiman bandara terkoordinasi (A1)';

    public function handle(AllocateSeasonalSlotsAction $action): int
    {
        $season = $this->argument('season');
        $this->info("Menjalankan perencanaan alokasi slot musiman untuk: {$season}");

        $airports = AviationAirport::where('slot_coordinated', true)->get();
        foreach ($airports as $airport) {
            $result = $action->execute($airport->id, $season);
            $this->line("- Bandara {$airport->iata_code}: Sisa kolam {$result->remainingPool} slot.");
        }

        $this->info('Perencanaan slot musiman berhasil diselesaikan.');

        return self::SUCCESS;
    }
}
