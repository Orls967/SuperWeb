<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MfgQmsAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'mfg:qms-audit';
    }

    public function corrupt(): string
    {
        DB::table('mfg_gauges')->insert([
            'code' => 'GAUGE-CORRUPT-'.Str::random(5),
            'name' => 'Alat Ukur Kadaluarsa Fixture',
            'is_active' => 1,
            'calibration_due' => now()->subDays(30)->toDateString(), // Kalibrasi sudah lewat
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'SELISIH';
    }
}
