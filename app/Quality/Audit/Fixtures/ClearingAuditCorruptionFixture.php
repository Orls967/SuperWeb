<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;

final class ClearingAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'clearing:audit';
    }

    public function corrupt(): string
    {
        DB::table('tf_crossborder_escrows')->insert([
            'escrow_code' => 'CBE-CORRUPT-FIXTURE',
            'importer_id' => 9999,
            'exporter_id' => 8888,
            'stablecoin_asset' => 'USDC',
            'amount_cents' => 100_000,
            'locked_fx_rate_idr' => 16_000,
            'bl_number' => 'BL-CORRUPT-UNVERIFIED',
            'bl_document_hash' => 'HASH-CORRUPT-DOC',
            'pod_chain_hash' => null, // violation: status is RELEASED but pod_chain_hash is NULL
            'status' => 'RELEASED',
            'released_at' => '2026-10-19 12:00:00',
            'created_at' => '2026-10-19 12:00:00',
            'updated_at' => '2026-10-19 12:00:00',
        ]);

        return 'clearing.unverified_pod_release';
    }
}
