<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Fleet\Domain\Models\FleetContract;

final class MobAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'mob:audit';
    }

    public function corrupt(): string
    {
        // Corrupt contract in Fleet sub-domain: monthly * duration mismatch
        FleetContract::create([
            'contract_number' => 'MOB-CORRUPT-'.Str::random(4),
            'party_id' => 999,
            'duration_months' => 12,
            'total_units' => 0,
            'monthly_rental_idr' => 10000000,
            'total_lease_value_idr' => 999999999, // Corrupted: mismatch with monthly * duration
            'accumulated_amortized_idr' => 0,
            'max_downtime_hours_per_month' => 24,
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addMonths(12),
            'status' => 'active',
        ]);

        return 'Consolidated Mobility Audit FAILED';
    }
}
