<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IntlAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'intl:audit';
    }

    public function corrupt(): string
    {
        $entityId = DB::table('intl_foreign_entities')->insertGetId([
            'entity_code' => 'ENT-CORRUPT-'.Str::random(5),
            'legal_name' => 'Global Corp Pte Ltd',
            'jurisdiction_country' => 'SGP',
            'registration_number' => 'SG12345678',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('intl_joint_ventures')->insert([
            'jv_code' => 'JV-CORRUPT-'.Str::random(5),
            'foreign_entity_id' => $entityId,
            'name' => 'PT Joint Venture Sentosa',
            'jv_type' => 'equity',
            'local_share_percent' => 60.0,
            'foreign_share_percent' => 60.0, // Total share 120% (melanggar invariant 100%)
            'total_committed_capital_idr' => 1000000000,
            'paid_in_capital_idr' => 500000000,
            'minority_veto_rights' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'International cooperation audit FAILED';
    }
}
