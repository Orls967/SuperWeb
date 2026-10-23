<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PtnAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ptn:audit';
    }

    public function corrupt(): string
    {
        $partnerId = (string) Str::uuid();
        DB::table('ptn_partners')->insert([
            'id' => $partnerId,
            'code' => 'PTN-CORRUPT-'.Str::random(5),
            'name' => 'Mitra Uji Korupsi',
            'kind' => 'channel',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ptn_revenue_shares')->insert([
            'id' => (string) Str::uuid(),
            'partner_id' => $partnerId,
            'period' => '2026-10',
            'gross_revenue_idr' => 10000000,
            'deductible_cost_idr' => 2000000,
            'net_base_idr' => 8000000,
            'share_rate_percent' => 20.0,
            'share_amount_idr' => 1600000,
            'status' => 'paid',
            'ledger_transaction_id' => null, // Status paid tetapi tanpa transaksi ledger
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'revenue share paid tanpa transaksi ledger';
    }
}
