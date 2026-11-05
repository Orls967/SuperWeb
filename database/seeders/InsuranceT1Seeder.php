<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Insurance\Domain\Models\InsuranceActuarialTriangle;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\InsuranceProductPenuh;

final class InsuranceT1Seeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat katalog produk 7 lini
        $products = [
            [
                'product_code' => 'INS-AUTO-01',
                'name' => 'Comprehensive Motor Vehicle Protection',
                'line_category' => 'VEHICLE',
                'base_rate_bps' => 250, // 2.5%
                'min_premium_idr' => 750000,
                'deductible_bps' => 1000,
                'quota_share_pct' => 40,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_code' => 'INS-PROP-01',
                'name' => 'Industrial All Risk & Property',
                'line_category' => 'PROPERTY',
                'base_rate_bps' => 150, // 1.5%
                'min_premium_idr' => 1500000,
                'deductible_bps' => 1000,
                'quota_share_pct' => 50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_code' => 'INS-CARGO-01',
                'name' => 'Marine Cargo & Ocean Transit',
                'line_category' => 'MARINE_CARGO',
                'base_rate_bps' => 80, // 0.8%
                'min_premium_idr' => 500000,
                'deductible_bps' => 500,
                'quota_share_pct' => 40,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_code' => 'INS-HEALTH-01',
                'name' => 'Corporate Executive Health',
                'line_category' => 'HEALTH',
                'base_rate_bps' => 350, // 3.5%
                'min_premium_idr' => 2000000,
                'deductible_bps' => 0,
                'quota_share_pct' => 30,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        InsuranceProductPenuh::upsert($products, ['product_code']);
        $product = InsuranceProductPenuh::where('product_code', 'INS-AUTO-01')->first();

        // 2. Seeding 5000 polis
        $policyRows = [];
        $now = now();
        $startDate = $now->toDateString();
        $endDate = $now->copy()->addYear()->toDateString();

        for ($i = 1; $i <= 5000; $i++) {
            $sumInsured = 100000000 + ($i * 10000); // Rp100jt++
            $gross = max(750000, (int) intdiv($sumInsured * 250, 10000));
            $ceded = (int) intdiv($gross * 40, 100);
            $retained = $gross - $ceded;

            $policyRows[] = [
                'policy_number' => sprintf('POL-T1-%06d', $i),
                'product_id' => $product->id,
                'customer_party_ref' => 1000 + ($i % 500),
                'sum_insured_idr' => $sumInsured,
                'gross_premium_idr' => $gross,
                'ceded_premium_idr' => $ceded,
                'retained_premium_idr' => $retained,
                'starts_at' => $startDate,
                'expires_at' => $endDate,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($i % 1000 === 0) {
                InsurancePolicyPenuh::insert($policyRows);
                $policyRows = [];
            }
        }
        if ($policyRows !== []) {
            InsurancePolicyPenuh::insert($policyRows);
        }

        // 3. Seeding 500 klaim
        $claimRows = [];
        $policyIds = InsurancePolicyPenuh::limit(500)->pluck('id')->toArray();
        foreach ($policyIds as $idx => $pId) {
            $claimed = 5000000 + ($idx * 50000);
            $approved = (int) intdiv($claimed * 90, 100);
            $subrogation = 500000;
            $reins = (int) intdiv(($approved - $subrogation) * 40, 100);
            $net = $approved - $subrogation - $reins;

            $claimRows[] = [
                'claim_number' => sprintf('CLM-T1-%06d', $idx + 1),
                'policy_id' => $pId,
                'incident_date' => $startDate,
                'incident_description' => "Incident simulation claim {$idx}",
                'claimed_amount_idr' => $claimed,
                'approved_amount_idr' => $approved,
                'subrogation_recovered_idr' => $subrogation,
                'reinsurance_recovered_idr' => $reins,
                'net_payout_idr' => $net,
                'status' => 'paid',
                'approved_by_user_ref' => 1,
                'approved_at' => $now,
                'idempotency_key' => sprintf('t1:clm:%d', $idx + 1),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        InsuranceClaimPenuh::insert($claimRows);

        // 4. Data segitiga aktuaria historis (3 origin years)
        $triangles = [
            ['line_category' => 'VEHICLE', 'accident_year' => 2024, 'development_year' => 0, 'cumulative_paid_idr' => 1000000000],
            ['line_category' => 'VEHICLE', 'accident_year' => 2024, 'development_year' => 1, 'cumulative_paid_idr' => 1500000000],
            ['line_category' => 'VEHICLE', 'accident_year' => 2024, 'development_year' => 2, 'cumulative_paid_idr' => 1800000000],
            ['line_category' => 'VEHICLE', 'accident_year' => 2025, 'development_year' => 0, 'cumulative_paid_idr' => 1200000000],
            ['line_category' => 'VEHICLE', 'accident_year' => 2025, 'development_year' => 1, 'cumulative_paid_idr' => 1800000000],
            ['line_category' => 'VEHICLE', 'accident_year' => 2026, 'development_year' => 0, 'cumulative_paid_idr' => 1400000000],
        ];

        foreach ($triangles as $tr) {
            InsuranceActuarialTriangle::updateOrInsert(
                [
                    'line_category' => $tr['line_category'],
                    'accident_year' => $tr['accident_year'],
                    'development_year' => $tr['development_year'],
                ],
                [
                    'cumulative_paid_idr' => $tr['cumulative_paid_idr'],
                    'updated_at' => now(),
                ]
            );
        }
    }
}
