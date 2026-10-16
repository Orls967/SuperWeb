<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TfAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'tf:audit';
    }

    public function corrupt(): string
    {
        DB::table('tf_bank_guarantees')->insert([
            'id' => (string) Str::uuid(),
            'guarantee_number' => 'BG-CORRUPT-'.Str::random(5),
            'type' => 'PERFORMANCE_BOND',
            'applicant_name' => 'PT Mitra Utama',
            'beneficiary_name' => 'PT Korporasi Holding',
            'issuing_bank' => 'Bank Mandiri',
            'amount_idr' => 10000000,
            'claim_amount_idr' => 50000000, // Klaim melebihi jumlah garansi (melanggar invariant)
            'effective_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'status' => 'issued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'Trade Finance audit FAILED';
    }
}
