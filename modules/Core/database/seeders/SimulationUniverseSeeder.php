<?php

declare(strict_types=1);

namespace Modules\Core\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Actions\Simulation\TakeTwinSnapshotAction;
use Modules\Core\Contracts\SimulationRunner;

final class SimulationUniverseSeeder extends Seeder
{
    public function run(): void
    {
        /** @var SimulationRunner $runner */
        $runner = app(SimulationRunner::class);
        $snapshotAction = app(TakeTwinSnapshotAction::class);

        // 1. Populasi 12 Sesi Simulasi Multi-Skenario (Total melampaui 400 hari virtual)
        $seeds = [42, 101, 202, 303, 404, 505, 606, 707, 808, 909, 1111, 2222];
        $durations = [30, 30, 30, 30, 30, 30, 30, 45, 45, 45, 45, 70];
        $createdRunIds = [];

        foreach ($seeds as $idx => $s) {
            $runNum = $idx + 1;
            $days = $durations[$idx];
            $mode = ($idx % 2 === 0) ? 'sandbox' : 'live';

            $id = $runner->start("universe-run-{$runNum}", $days, $s, $mode);
            $createdRunIds[] = $id;
        }

        // 2. Populasi Digital Twin (200 entities: kendaraan, bed rs, kamar hotel, peti kemas)
        $anchorRunId = $createdRunIds[1] ?? $createdRunIds[0];

        for ($i = 1; $i <= 50; $i++) {
            $snapshotAction->execute('vehicle', "VH-{$i}", 1, ['odometer' => $i * 1000, 'battery' => 95], $anchorRunId);
            $snapshotAction->execute('hospital_bed', "BED-{$i}", 1, ['status' => 'occupied', 'patient_id' => "P-{$i}"], $anchorRunId);
            $snapshotAction->execute('hotel_room', "ROOM-{$i}", 1, ['status' => 'checked_in', 'daily_rate' => 750000], $anchorRunId);
            $snapshotAction->execute('container', "CONT-{$i}", 1, ['status' => 'in_terminal', 'free_days' => 5], $anchorRunId);
        }
    }
}
