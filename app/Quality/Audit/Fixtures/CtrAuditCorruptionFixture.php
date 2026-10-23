<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CtrAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ctr:audit';
    }

    public function corrupt(): string
    {
        $entityId = (string) Str::uuid();
        DB::table('pty_legal_entities')->insertOrIgnore([
            'id' => $entityId,
            'name' => 'PT Test Entity',
            'short_name' => 'PT Test',
            'entity_type' => 'company',
            'functional_currency' => 'IDR',
            'fiscal_year_start' => '01-01',
            'ledger_prefix' => 'TST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ctr_contracts')->insert([
            'id' => (string) Str::uuid(),
            'contract_number' => 'CTR-AUDIT-'.Str::random(5),
            'title' => 'Kontrak Korupsi Plafon',
            'contract_type' => 'commercial',
            'status' => 'active',
            'legal_entity_id' => $entityId,
            'total_value_idr' => 10000000,
            'used_value_idr' => 8888888, // Selisih dengan ctr_usage_ledger (0)
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'SELISIH';
    }
}
