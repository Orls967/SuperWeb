<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GroupAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'group:audit';
    }

    public function corrupt(): string
    {
        DB::table('ic_transactions')->insert([
            'transaction_code' => 'IC-CORRUPT-'.Str::random(5),
            'selling_entity' => 'HOLDING_CORP',
            'buying_entity' => 'HOLDING_CORP', // Entitas jual dan beli sama (pelanggaran transaksi mirror)
            'description' => 'Transaksi Mirror Korup',
            'currency' => 'IDR',
            'amount_idr' => 5000000,
            'sales_invoice_ref' => 'INV-CORRUPT-1',
            'purchase_bill_ref' => 'BILL-CORRUPT-1',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Intercompany audit FAILED';
    }
}
