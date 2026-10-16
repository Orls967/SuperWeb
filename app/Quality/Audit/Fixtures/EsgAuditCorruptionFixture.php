<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EsgAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'esg:audit';
    }

    public function corrupt(): string
    {
        DB::table('esg_emissions')->insert([
            'id' => (string) Str::uuid(),
            'emission_number' => 'EM-CORRUPT-'.Str::random(5),
            'entity_id' => (string) Str::uuid(),
            'scope' => 'SCOPE_1',
            'activity_type' => 'DIESEL_FUEL',
            'activity_data_amount' => 100.0,
            'activity_uom' => 'LITERS',
            'emission_factor' => 2.68,
            'co2e_kg' => 9999.0, // Seharusnya 268.0 kg (mismatch kalkulasi)
            'reporting_period' => '2026-10-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Emission calculation mismatch';
    }
}
