<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DistAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'dist:audit';
    }

    public function corrupt(): string
    {
        DB::table('dist_distributors')->insert([
            'id' => (string) Str::uuid(),
            'code' => 'DIST-CORRUPT-'.Str::random(5),
            'name' => 'Distributor Korupsi Piutang',
            'kind' => 'distributor',
            'tier' => 'gold',
            'status' => 'active',
            'credit_limit_idr' => 100000000,
            'credit_exposure_idr' => 55555555, // Selisih dengan dist_ar_invoices (0)
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'SELISIH';
    }
}
