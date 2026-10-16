<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ContractsVerifyChainCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'contracts:verify-chain';
    }

    public function corrupt(): string
    {
        $contractId = (string) Str::uuid();
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
            'id' => $contractId,
            'contract_number' => 'CTR-CORRUPT-'.Str::random(5),
            'title' => 'Kontrak Korup Rantai Hash',
            'contract_type' => 'commercial',
            'status' => 'draft',
            'legal_entity_id' => $entityId,
            'total_value_idr' => 1000000,
            'used_value_idr' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ctr_contract_versions')->insert([
            'id' => (string) Str::uuid(),
            'contract_id' => $contractId,
            'sequence' => 1,
            'change_type' => 'creation',
            'body' => 'Initial Body',
            'metadata' => json_encode(['author' => 'Auditor']),
            'hash' => 'invalid-hash-that-fails-chain-verification',
            'prev_hash' => 'GENESIS',
            'created_by_name' => 'Auditor',
            'created_at' => now(),
        ]);

        return 'RUSAK!';
    }
}
