<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProcAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'proc:audit';
    }

    public function corrupt(): string
    {
        $poId = (string) Str::uuid();
        DB::table('prc_purchase_orders')->insert([
            'id' => $poId,
            'number' => 'PO-CORRUPT-'.Str::random(5),
            'supplier_id' => 'SUP-1',
            'title' => 'PO Pengadaan Uji Korupsi',
            'kind' => 'standard',
            'status' => 'received',
            'version' => 1,
            'currency' => 'IDR',
            'total_amount' => 10000000,
            'received_amount' => 10000000,
            'expected_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('prc_receiving_reports')->insert([
            'id' => (string) Str::uuid(),
            'number' => 'GRN-CORRUPT-'.Str::random(5),
            'po_id' => $poId,
            'status' => 'draft', // status bukan accepted/closed menyebabkan unmatched GR/IR
            'received_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'SELISIH';
    }
}
