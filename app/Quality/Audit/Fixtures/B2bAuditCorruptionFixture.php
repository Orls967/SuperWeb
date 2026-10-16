<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class B2bAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'b2b:audit';
    }

    public function corrupt(): string
    {
        DB::table('b2b_escrow_accounts')->insert([
            'id' => (string) Str::uuid(),
            'escrow_number' => 'ESC-CORRUPT-'.Str::random(5),
            'reference_type' => 'AUCTION',
            'reference_id' => (string) Str::uuid(),
            'buyer_id' => (string) Str::uuid(),
            'seller_id' => (string) Str::uuid(),
            'deposit_amount_idr' => 1000000,
            'released_amount_idr' => 2000000, // over-released
            'refunded_amount_idr' => 0,
            'status' => 'held',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'over-released';
    }
}
