<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningEquipment;

final class MinAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'min:audit';
    }

    public function corrupt(): string
    {
        // Corrupt equipment: marked 'dispatched' but without any matching in_progress dispatch run
        MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => (string) Str::uuid(),
            'equipment_code' => 'DT-CORRUPT-'.Str::random(4),
            'type' => 'DUMP_TRUCK',
            'model' => 'Ghost Hauler 9000',
            'capacity_ton' => 100.0,
            'status' => 'dispatched', // Corrupted: no active dispatch run exists!
            'engine_hours' => 9999,
        ]);

        return 'Mining audit FAILED';
    }
}
