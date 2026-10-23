<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Hospital\Domain\Models\HospitalBed;

final class HspAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'hsp:audit';
    }

    public function corrupt(): string
    {
        // Corrupt bed state: marked OCCUPIED but current_encounter_id is null
        HospitalBed::create([
            'bed_code' => 'BED-CORRUPT-'.Str::random(4),
            'ward_name' => 'Corrupt Ward',
            'room_number' => 'CORRUPT-99',
            'bed_class' => 'VIP',
            'rate_per_day_idr' => 1000000,
            'status' => 'OCCUPIED',
            'current_encounter_id' => null,
        ]);

        return 'Hospital audit FAILED';
    }
}
