<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Insurance\Domain\Models\TakafulFund;
use Modules\Insurance\Domain\Models\TakafulParticipant;

final class TakafulAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ins:takaful-audit';
    }

    public function corrupt(): string
    {
        $fund = TakafulFund::firstOrCreate(
            ['fund_code' => 'TK-TABARRU-MAIN'],
            [
                'fund_type' => 'TABARRU',
                'name' => 'Dana Kebajikan Tabarru Utama',
                'balance_idr' => 1000000000,
                'total_contributions_idr' => 1000000000,
                'total_claims_paid_idr' => 200000000,
                'contingency_reserve_idr' => 100000000,
                'is_active' => true,
            ]
        );

        // Korupsi: buat peserta dengan total porsi tabarru + ujrah != 10000 bps
        TakafulParticipant::create([
            'participant_party_id' => '00000000-0000-0000-0000-000000000001',
            'policy_number' => 'POL-TK-CORRUPT-'.uniqid(),
            'fund_id' => $fund->id,
            'plan_type' => 'FAMILY_TAKAFUL',
            'contribution_annual_idr' => 5000000,
            'tabarru_portion_bps' => 7000,
            'ujrah_portion_bps' => 2000, // Total hanya 9000 bps (mismatch 1000 bps!)
            'sum_covered_idr' => 100000000,
            'status' => 'active',
            'effective_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        return 'Audit dua-sumber Takaful & Agri GAGAL! Mismatch: Deficit=0, Ratio=1';
    }
}
