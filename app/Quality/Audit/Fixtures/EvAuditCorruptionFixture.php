<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Models\User;
use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Mobility\Ev\database\seeders\EvDemoSeeder;
use Modules\Mobility\Ev\Domain\Models\EvCharger;
use Modules\Mobility\Ev\Domain\Models\EvSession;

final class EvAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ev:audit';
    }

    public function corrupt(): string
    {
        (new EvDemoSeeder)->run();

        $charger = EvCharger::firstOrFail();
        $vehicle = Vehicle::firstOrFail();
        $user = User::firstOrFail();

        // Corrupt completed session: 20 kWh * 2500 IDR should be 50,000 IDR, but recorded as 999,999,999 IDR!
        EvSession::create([
            'session_code' => 'EV-CORRUPT-'.Str::random(4),
            'charger_id' => $charger->id,
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'reserved_from' => now()->subHours(2),
            'reserved_to' => now()->subHour(),
            'tariff_per_kwh_idr' => 2500,
            'energy_wh' => 20000,
            'total_cost_idr' => 999999999, // Corrupted: mismatch with energy * tariff!
            'final_soh_pct' => 95.0,
            'completed_at' => now(),
            'status' => 'completed',
        ]);

        return 'EV audit FAILED';
    }
}
