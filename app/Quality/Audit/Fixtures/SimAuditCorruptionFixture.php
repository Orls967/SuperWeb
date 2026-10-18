<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Core\Domain\Models\SimTwinState;

final class SimAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'sim:audit';
    }

    public function corrupt(): string
    {
        SimTwinState::create([
            'entity_type' => 'vehicle',
            'entity_id' => 'CORRUPT-VEH-'.uniqid(),
            'state_hash' => 'forged_invalid_sha256_hash_value_for_audit_fixture',
            'prev_hash' => 'forged_prev_hash',
            'state' => ['mileage' => 999999, 'battery_soh' => 12],
            'valid_from' => now(),
        ]);

        return 'sim:audit FAILED: Digital twin hash-chain corruption detected';
    }
}
