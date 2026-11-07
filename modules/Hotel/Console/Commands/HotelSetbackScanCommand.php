<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\RunSetbackScanAction;
use Modules\Hotel\Domain\Models\HotelProperty;

final class HotelSetbackScanCommand extends Command
{
    protected $signature = 'hotel:setback-scan';

    protected $description = 'Memindai sensor okupansi kamar dan mengaktifkan HVAC setback H4';

    public function handle(RunSetbackScanAction $action): int
    {
        $properties = HotelProperty::all();
        $totalWh = 0;

        foreach ($properties as $prop) {
            $res = $action->execute((int) $prop->id);
            $totalWh += $res['total_saved_wh'];
            $this->line("Properti {$prop->name}: {$res['setback_rooms']} kamar setback aktif, hemat {$res['total_saved_wh']} Wh.");
        }

        $this->info("Pemindaian setback selesai. Total energi dihemat: {$totalWh} Wh.");
        return self::SUCCESS;
    }
}
