<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AstAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ast:audit';
    }

    public function corrupt(): string
    {
        $catId = DB::table('ast_categories')->insertGetId([
            'code' => 'CAT-'.Str::random(4),
            'name' => 'Kategori Uji',
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ast_assets')->insert([
            'id' => (string) Str::uuid(),
            'asset_number' => 'AST-CORRUPT-'.Str::random(5),
            'asset_tag' => 'TAG-'.Str::random(5),
            'name' => 'Aset Uji Korupsi',
            'category_id' => $catId,
            'acquisition_cost_idr' => 10000000,
            'landed_cost_idr' => 0,
            'accumulated_depreciation_idr' => 99999999, // Selisih dengan jadwal penyusutan (0)
            'book_value_idr' => 10000000,
            'status' => 'in_use',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'SELISIH';
    }
}
