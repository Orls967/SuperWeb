<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\ExecuteHousekeepingPlanAction;
use Modules\Hotel\Domain\Models\HotelProperty;

final class HotelHousekeepingPlanCommand extends Command
{
    protected $signature = 'hotel:housekeeping-plan {date : Tanggal Tugas Y-m-d}';

    protected $description = 'Menyusun rute kerja harian petugas housekeeping H3';

    public function handle(ExecuteHousekeepingPlanAction $action): int
    {
        $date = (string) $this->argument('date');
        $properties = HotelProperty::all();

        foreach ($properties as $prop) {
            $res = $action->execute((int) $prop->id, $date);
            $this->line("Properti {$prop->name}: {$res['total_tasks']} tugas dialokasikan ke {$res['assigned_staff_count']} petugas.");
        }

        $this->info('Rencana rute housekeeping selesai disusun.');

        return self::SUCCESS;
    }
}
