<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlmAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'plm:audit';
    }

    public function corrupt(): string
    {
        DB::table('plm_change_orders')->insert([
            'id' => (string) Str::uuid(),
            'eco_number' => 'ECO-CORRUPT-'.Str::random(5),
            'ebom_id' => (string) Str::uuid(),
            'title' => 'ECO dengan Hash Korup',
            'reason' => 'Pengujian Audit',
            'cost_impact_idr' => 1000000,
            'disposition' => 'scrap',
            'prev_hash' => 'GENESIS',
            'hash' => 'corrupted-eco-hash-that-fails-verification',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'PLM audit FAILED';
    }
}
