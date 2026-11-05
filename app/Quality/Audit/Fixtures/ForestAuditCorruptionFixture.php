<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;

final class ForestAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'for:audit';
    }

    public function corrupt(): string
    {
        // Masukkan data tebangan kayu ilegal yang melebihi kuota petak
        DB::table('for_concession_areas')->insert([
            'plot_code' => 'PLT-CORRUPT-FOR',
            'concession_name' => 'Concession Corrupt',
            'area_hectares' => 100,
            'annual_quota_m3' => 500,
            'harvested_volume_m3' => 600, // Melebihi kuota 500
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'kuota petak';
    }
}
