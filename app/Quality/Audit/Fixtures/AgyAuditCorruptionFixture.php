<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AgyAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'agy:audit';
    }

    public function corrupt(): string
    {
        $agentId = (string) Str::uuid();
        DB::table('agy_agents')->insert([
            'id' => $agentId,
            'code' => 'AGT-'.Str::random(5),
            'name' => 'Agen Uji Korupsi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agy_payouts')->insert([
            'agent_id' => $agentId,
            'number' => 'PAY-CORRUPT-'.Str::random(5),
            'period' => '2026-10',
            'gross_idr' => 1000000,
            'withheld_tax_idr' => 50000,
            'net_idr' => 2000000, // Seharusnya 950.000 (net != gross - tax)
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 'payout.net';
    }
}
