<?php

declare(strict_types=1);

namespace Modules\Ev\database\seeders;

use App\Models\User;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Banking\Domain\Models\LedgerAccount;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Ev\Domain\Models\EvCharger;
use Modules\Ev\Domain\Models\EvSession;
use Modules\Ev\Domain\Models\EvStation;

class EvDemoSeeder extends Seeder
{
    public function run(): void
    {
        (new LedgerAccountsSeeder)->run();

        $user = User::firstOrCreate(
            ['email' => 'ev_driver@example.com'],
            ['name' => 'EV Fleet Driver', 'password' => bcrypt('secret123')]
        );

        // Ensure user wallet exists in ledger
        LedgerAccount::firstOrCreate(
            ['code' => "wallet:user:{$user->id}:IDR"],
            [
                'name' => "User {$user->id} Wallet IDR",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        $vehicle = Vehicle::firstOrCreate(
            ['vin' => 'VIN-IONIQ-EV-001'],
            [
                'user_id' => $user->id,
                'plate_number' => 'B 5555 EV',
                'color' => 'White',
                'odometer_km' => 5000,
                'status' => 'active',
            ]
        );

        $station = EvStation::firstOrCreate(
            ['station_code' => 'SPKLU-PLN-SUDIRMAN'],
            [
                'name' => 'SPKLU PLN Gambir - Sudirman Hub',
                'location_type' => 'PUBLIC_FAST_CHARGER',
                'address' => 'Jl. Jenderal Sudirman No. 1, Jakarta Pusat',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'is_active' => true,
            ]
        );

        $charger = EvCharger::firstOrCreate(
            ['charger_code' => 'CHG-CCS2-01'],
            [
                'station_id' => $station->id,
                'type' => 'DC_FAST',
                'max_kw' => 150,
                'status' => 'available',
            ]
        );

        EvSession::firstOrCreate(
            ['session_code' => 'EV-DEMO-001'],
            [
                'charger_id' => $charger->id,
                'vehicle_id' => $vehicle->id,
                'user_id' => $user->id,
                'reserved_from' => now()->subHour(),
                'reserved_to' => now(),
                'tariff_per_kwh_idr' => 2500,
                'energy_wh' => 25000,
                'total_cost_idr' => 62500,
                'final_soh_pct' => 98.5,
                'completed_at' => now(),
                'status' => 'completed',
            ]
        );
    }
}
