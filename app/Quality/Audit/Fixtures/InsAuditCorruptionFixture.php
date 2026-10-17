<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Models\User;
use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Insurance\Domain\Models\InsurancePolicy;
use Modules\Insurance\Domain\Models\InsuranceProduct;

final class InsAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ins:audit';
    }

    public function corrupt(): string
    {
        $user = User::first() ?? User::factory()->create();

        $product = InsuranceProduct::firstOrCreate(
            ['product_code' => 'INS-CORRUPT-PROD'],
            [
                'name' => 'Corrupt Insurance Product',
                'premium_amount_idr' => 50000,
                'max_payout_idr' => 200000,
                'trigger_event_type' => 'ACCIDENT',
                'is_active' => true,
            ]
        );

        // Corrupted policy: product premium is 50,000, but policy premium_paid_idr recorded as 999,999,999!
        InsurancePolicy::create([
            'policy_number' => 'POL-CORRUPT-'.Str::random(4),
            'product_id' => $product->id,
            'user_id' => $user->id,
            'subject_ref_type' => 'VEHICLE',
            'subject_ref_id' => 'V-999',
            'premium_paid_idr' => 999999999, // Corrupted: mismatch with product premium!
            'coverage_limit_idr' => 200000,
            'starts_at' => now(),
            'expires_at' => now()->addDays(7),
            'status' => 'active',
        ]);

        return 'Insurance audit FAILED';
    }
}
