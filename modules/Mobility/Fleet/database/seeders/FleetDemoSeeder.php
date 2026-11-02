<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\database\seeders;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Core\Contracts\Vehicle;
use Modules\Mobility\Fleet\Domain\Models\FleetContract;
use Modules\Mobility\Fleet\Domain\Models\FleetContractUnit;

class FleetDemoSeeder extends Seeder
{
    public function run(): void
    {
        (new LedgerAccountsSeeder)->run();

        $user = User::firstOrCreate(
            ['email' => 'fleet_driver@example.com'],
            ['name' => 'Fleet Driver', 'password' => bcrypt('secret123')]
        );

        $vehicle = Vehicle::firstOrCreate(
            ['vin' => 'VIN-HILUX-FLT-001'],
            [
                'user_id' => $user->id,
                'plate_number' => 'B 9900 FLT',
                'color' => 'Silver',
                'odometer_km' => 2000,
                'status' => 'active',
            ]
        );

        $partyId = 501;
        // Ensure AR account exists for this party
        LedgerAccount::firstOrCreate(
            ['code' => "ar:fleet:party:{$partyId}:IDR"],
            [
                'name' => "AR Fleet Leasing Party {$partyId}",
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        $contract = FleetContract::firstOrCreate(
            ['contract_number' => 'FLT-DEMO-001'],
            [
                'party_id' => $partyId,
                'duration_months' => 12,
                'total_units' => 1,
                'monthly_rental_idr' => 15000000,
                'total_lease_value_idr' => 180000000,
                'accumulated_amortized_idr' => 0,
                'max_downtime_hours_per_month' => 24,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(12),
                'status' => 'active',
            ]
        );

        FleetContractUnit::firstOrCreate(
            ['contract_id' => $contract->id, 'vehicle_id' => $vehicle->id],
            [
                'baseline_odometer_km' => 2000,
                'max_km_per_year' => 30000,
                'status' => 'assigned',
            ]
        );
    }
}
