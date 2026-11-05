<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Services;

use Modules\Insurance\Domain\Models\EmbedPolicy;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;

final class CustomerHubService
{
    /**
     * Mengambil portofolio terpadu seluruh polis aktif milik nasabah lintas lini konvensional & embedded.
     *
     * @return array{
     *     customer_party_id: string,
     *     active_policy_count: int,
     *     total_sum_insured_idr: int,
     *     bundling_discount_bps: int,
     *     policies: list<array<string, mixed>>
     * }
     */
    public function getPortfolio(string $customerPartyId): array
    {
        // 1. Polis penuh
        $corePolicies = InsurancePolicyPenuh::query()
            ->where('customer_party_ref', (int) $customerPartyId)
            ->where('status', 'active')
            ->get();

        // 2. Polis embedded
        $embedPolicies = EmbedPolicy::query()
            ->where('customer_party_id', $customerPartyId)
            ->where('status', 'active')
            ->with('product')
            ->get();

        $unified = [];
        $totalSi = 0;

        foreach ($corePolicies as $p) {
            $totalSi += $p->sum_insured_idr;
            $unified[] = [
                'type' => 'CORE',
                'policy_number' => $p->policy_number,
                'product_name' => $p->product?->name ?? 'Polis Utama',
                'sum_insured_idr' => $p->sum_insured_idr,
                'starts_at' => $p->starts_at?->toIso8601String(),
                'expires_at' => $p->expires_at?->toIso8601String(),
            ];
        }

        foreach ($embedPolicies as $ep) {
            $totalSi += $ep->sum_insured_idr;
            $unified[] = [
                'type' => 'EMBEDDED',
                'policy_number' => $ep->policy_number,
                'product_name' => $ep->product?->name ?? 'Embedded Insurance',
                'sum_insured_idr' => $ep->sum_insured_idr,
                'context' => "{$ep->context_type}:{$ep->context_ref}",
                'starts_at' => $ep->starts_at->toIso8601String(),
                'expires_at' => $ep->expires_at->toIso8601String(),
            ];
        }

        $count = count($unified);
        // Kalkulasi diskon bundling dinamis: 2 polis = 500 bps (5%), >= 3 polis = 1000 bps (10%)
        $discountBps = match (true) {
            $count >= 3 => 1000,
            $count === 2 => 500,
            default => 0,
        };

        return [
            'customer_party_id' => $customerPartyId,
            'active_policy_count' => $count,
            'total_sum_insured_idr' => $totalSi,
            'bundling_discount_bps' => $discountBps,
            'policies' => $unified,
        ];
    }
}
