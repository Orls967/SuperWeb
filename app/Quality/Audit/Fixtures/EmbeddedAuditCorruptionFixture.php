<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Insurance\Domain\Models\EmbedPolicy;
use Modules\Insurance\Domain\Models\EmbedProduct;

final class EmbeddedAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ins:embedded-audit';
    }

    public function corrupt(): string
    {
        $prod = EmbedProduct::firstOrCreate(
            ['code' => 'TEST-HOTEL-CORRUPT'],
            [
                'line_domain' => 'HTL',
                'name' => 'Corrupted Hotel Policy',
                'coverage_type' => 'CANCELLATION',
                'default_sum_insured_idr' => 1000000,
                'premium_rate_bps' => 500,
                'min_premium_idr' => 10000,
                'is_active' => true,
            ]
        );

        // Korupsi: polis memiliki broker_commission_idr tetapi tidak ada baris komisi di market_commissions
        EmbedPolicy::create([
            'policy_number' => 'POL-EMB-CORRUPT-'.uniqid(),
            'embedded_product_id' => $prod->id,
            'customer_party_id' => '00000000-0000-0000-0000-000000000001',
            'broker_party_id' => '00000000-0000-0000-0000-000000000002',
            'context_type' => 'booking',
            'context_ref' => 'HTL-CORRUPT-001',
            'sum_insured_idr' => 1000000,
            'premium_idr' => 50000,
            'broker_commission_idr' => 10000, // Hilang di tabel komisi!
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        return 'Audit embedded GAGAL';
    }
}
