<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AstVerifyChainCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ast:verify-chain';
    }

    public function corrupt(): string
    {
        $assetId = (string) Str::uuid();

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
            'id' => $assetId,
            'asset_number' => 'AST-CHAIN-'.Str::random(5),
            'asset_tag' => 'TAG-'.Str::random(5),
            'name' => 'Aset Uji Rantai Hash',
            'category_id' => $catId,
            'acquisition_cost_idr' => 5000000,
            'landed_cost_idr' => 0,
            'accumulated_depreciation_idr' => 0,
            'book_value_idr' => 5000000,
            'status' => 'in_use',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ast_events')->insert([
            'id' => (string) Str::uuid(),
            'asset_id' => $assetId,
            'sequence' => 1,
            'event_type' => 'acquire',
            'payload' => json_encode(['action' => 'initial_acquisition']),
            'prev_hash' => 'GENESIS',
            'hash' => 'invalid-hash-corrupted-intentionally',
            'created_by_name' => 'Auditor Fixture',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Rantai rusak';
    }
}
