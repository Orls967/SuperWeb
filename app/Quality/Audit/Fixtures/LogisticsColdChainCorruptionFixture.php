<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Logistics\Domain\Models\Autonomous\TempBreachHold;

final class LogisticsColdChainCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'lgx:audit-coldchain';
    }

    public function corrupt(): string
    {
        TempBreachHold::create([
            'breach_code' => 'BRC-CORRUPT-COLD-999',
            'shipment_code' => 'SHP-CORRUPT-999',
            'carrier_id' => 777,
            'recorded_temp_c' => 15.0,
            'max_allowed_temp_c' => 4.0,
            'duration_minutes' => 30,
            'hold_amount_idr' => 10_000_000,
            'hash_proof' => 'tampered_invalid_hash_proof_string',
            'status' => 'HELD',
        ]);

        return 'BRC-CORRUPT-COLD-999';
    }
}
