<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Egy\Domain\Models\GridNode;

final class EgyAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'egy:audit';
    }

    public function corrupt(): string
    {
        GridNode::create([
            'id' => (string) Str::uuid(),
            'node_code' => 'NODE-CORRUPT-99',
            'name' => 'Substation Overloaded',
            'substation_region' => 'JAVA_BALI_GRID',
            'nominal_voltage_kv' => 150.0,
            'current_load_mw' => 500.0,
            'peak_capacity_mw' => 100.0,
        ]);

        return 'Energy audit FAILED';
    }
}
