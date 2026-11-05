<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Insurance\Domain\Models\HealthClaim;
use Modules\Insurance\Domain\Models\HealthPolicy;

final class LifeHealthAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ins:life-health-audit';
    }

    public function corrupt(): string
    {
        $policy = HealthPolicy::first();
        if ($policy === null) {
            $policy = HealthPolicy::create([
                'policy_number' => 'POL-HLTH-FIXTURE',
                'member_party_id' => '00000000-0000-0000-0000-000000000001',
                'plan_tier' => 'GOLD',
                'annual_limit_idr' => 100000000,
                'annual_utilized_idr' => 0,
                'room_daily_limit_idr' => 1000000,
                'deductible_idr' => 0,
                'status' => 'active',
                'effective_date' => now()->toDateString(),
                'expiry_date' => now()->addYear()->toDateString(),
            ]);
        }

        // Korupsi: buat klaim kesehatan di mana approved + patient_share != incurred_amount (selisih manipulasi)
        HealthClaim::create([
            'claim_number' => 'CLM-CORRUPT-HLTH-'.uniqid(),
            'health_policy_id' => $policy->id,
            'claim_type' => 'REIMBURSEMENT',
            'incurred_amount_idr' => 5000000,
            'approved_amount_idr' => 3000000,
            'patient_share_idr' => 1000000, // Total hanya 4jt, mismatch 1jt!
            'status' => 'settled',
        ]);

        return 'Audit dua-sumber Life & Health GAGAL! Mismatch: Beneficiary=0, Claims=1';
    }
}
