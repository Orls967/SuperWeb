<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Insurance\Domain\Enums\CatBondStatus;
use Modules\Insurance\Domain\Enums\TreatyStatus;
use Modules\Insurance\Domain\Enums\TreatyType;
use Modules\Insurance\Domain\Models\CatBond;
use Modules\Insurance\Domain\Models\InsuranceActuarialTriangle;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsuranceParameter;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\InsuranceProductPenuh;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;
use Modules\Insurance\Domain\Models\ReinsuranceTreatyPanel;
use Modules\Party\Domain\Enums\PartyType;
use Modules\Party\Domain\Models\Party;
use Modules\Rwa\Contracts\CatBondTokenRegistry;

final class InsuranceT1Seeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $startDate = $now->toDateString();
        $endDate = $now->copy()->addYear()->toDateString();

        // 1. Parameter aktuaria berversi (G7-D1, S1)
        $this->seedParameters();

        // 2. 8 Reinsurer Parties (D157-1)
        $reinsurers = $this->seedReinsurers();

        // 3. 6 Program Treaty Reasuransi (D157-1)
        $this->seedTreaties($reinsurers, $startDate, $endDate);

        // 4. Katalog produk 7 lini
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        InsuranceProductPenuh::upsert($products, ['product_code']);
        $product = InsuranceProductPenuh::where('product_code', 'INS-AUTO-01')->firstOrFail();

        // 5. Cat Bond (D157-6)
        $this->seedCatBond();

        // 6. Seeding Polis
        $policyCount = app()->environment('testing') ? 200 : 20000;
        $policyRows = [];

        for ($i = 1; $i <= $policyCount; $i++) {
            $sumInsured = 100000000 + ($i * 10000); // Rp100jt++
            $gross = max(750000, (int) intdiv($sumInsured * 250, 10000));
            $ceded = (int) intdiv($gross * 40, 100);
            $retained = $gross - $ceded;

            $policyRows[] = [
                'policy_number' => sprintf('POL-T1-%06d', $i),
                'product_id' => $product->id,
                'customer_party_ref' => 1000 + ($i % 500),
                'region_code' => ($i % 3 === 0) ? 'REG-JKT' : (($i % 3 === 1) ? 'REG-JBR' : 'REG-JTM'),
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

        // 7. Seeding klaim
        $claimCount = app()->environment('testing') ? 20 : 500;
        $claimRows = [];
        $policyIds = InsurancePolicyPenuh::limit($claimCount)->pluck('id')->toArray();
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

        // 8. Segitiga aktuaria
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
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * @return list<Party>
     */
    private function seedReinsurers(): array
    {
        $names = [
            'PT Reasuransi Indonesia Utama (Persero)',
            'PT Maskapai Reasuransi Indonesia Tbk',
            'PT Reasuransi Nusantara Makmur',
            'Swiss Re Asia Pte Ltd',
            'Munich Reinsurance Company',
            'Hannover Rück SE',
            'SCOR Global P&C',
            'Asia Capital Reinsurance',
        ];

        $reinsurers = [];
        foreach ($names as $idx => $rName) {
            $party = Party::firstOrCreate(
                ['name' => $rName],
                [
                    'name_normalized' => strtolower(trim($rName)),
                    'type' => PartyType::Company,
                    'short_name' => 'REINS-'.($idx + 1),
                    'status' => \Modules\Party\Domain\Enums\PartyStatus::Verified,
                    'is_active' => true,
                ]
            );
            $reinsurers[] = $party;
        }

        return $reinsurers;
    }

    /**
     * @param  list<Party>  $reinsurers
     */
    private function seedTreaties(array $reinsurers, string $startDate, string $endDate): void
    {
        // 1. Quota share 40% untuk VEHICLE (sesuai ekspektasi InsuranceAcceptanceTest 156)
        $t1 = ReinsuranceTreaty::updateOrCreate(
            ['treaty_code' => 'TRT-2026-0001'],
            [
                'name' => 'Motor Vehicle Quota Share Treaty 2026',
                'type' => TreatyType::QuotaShare,
                'line_category' => 'VEHICLE',
                'status' => TreatyStatus::Active,
                'inception_date' => $startDate,
                'expiry_date' => $endDate,
                'quota_share_bps' => 4000, // 40%
                'commission_bps' => 1500,  // 15%
            ]
        );
        $t1->panel()->delete();
        ReinsuranceTreatyPanel::create(['treaty_id' => $t1->id, 'reinsurer_party_id' => $reinsurers[0]->id, 'share_bps' => 6000]);
        ReinsuranceTreatyPanel::create(['treaty_id' => $t1->id, 'reinsurer_party_id' => $reinsurers[1]->id, 'share_bps' => 4000]);

        // 2. Quota share 50% untuk PROPERTY
        $t2 = ReinsuranceTreaty::updateOrCreate(
            ['treaty_code' => 'TRT-2026-0002'],
            [
                'name' => 'Property Quota Share Treaty 2026',
                'type' => TreatyType::QuotaShare,
                'line_category' => 'PROPERTY',
                'status' => TreatyStatus::Active,
                'inception_date' => $startDate,
                'expiry_date' => $endDate,
                'quota_share_bps' => 5000,
                'commission_bps' => 2000,
            ]
        );
        $t2->panel()->delete();
        ReinsuranceTreatyPanel::create(['treaty_id' => $t2->id, 'reinsurer_party_id' => $reinsurers[2]->id, 'share_bps' => 10000]);

        // 3. Surplus 5 lines untuk PROPERTY
        $t3 = ReinsuranceTreaty::updateOrCreate(
            ['treaty_code' => 'TRT-2026-0003'],
            [
                'name' => 'Industrial Property Surplus Treaty 2026',
                'type' => TreatyType::Surplus,
                'line_category' => 'PROPERTY',
                'status' => TreatyStatus::Active,
                'inception_date' => $startDate,
                'expiry_date' => $endDate,
                'surplus_retention_idr' => 100000000,
                'surplus_lines' => 5,
                'commission_bps' => 2000,
            ]
        );
        $t3->panel()->delete();
        ReinsuranceTreatyPanel::create(['treaty_id' => $t3->id, 'reinsurer_party_id' => $reinsurers[3]->id, 'share_bps' => 10000]);

        // 4. Quota share 40% untuk MARINE_CARGO
        $t4 = ReinsuranceTreaty::updateOrCreate(
            ['treaty_code' => 'TRT-2026-0004'],
            [
                'name' => 'Marine Cargo Quota Share 2026',
                'type' => TreatyType::QuotaShare,
                'line_category' => 'MARINE_CARGO',
                'status' => TreatyStatus::Active,
                'inception_date' => $startDate,
                'expiry_date' => $endDate,
                'quota_share_bps' => 4000,
                'commission_bps' => 1500,
            ]
        );
        $t4->panel()->delete();
        ReinsuranceTreatyPanel::create(['treaty_id' => $t4->id, 'reinsurer_party_id' => $reinsurers[4]->id, 'share_bps' => 10000]);

        // 5. Quota share 30% untuk HEALTH
        $t5 = ReinsuranceTreaty::updateOrCreate(
            ['treaty_code' => 'TRT-2026-0005'],
            [
                'name' => 'Corporate Health Quota Share 2026',
                'type' => TreatyType::QuotaShare,
                'line_category' => 'HEALTH',
                'status' => TreatyStatus::Active,
                'inception_date' => $startDate,
                'expiry_date' => $endDate,
                'quota_share_bps' => 3000,
                'commission_bps' => 1000,
            ]
        );
        $t5->panel()->delete();
        ReinsuranceTreatyPanel::create(['treaty_id' => $t5->id, 'reinsurer_party_id' => $reinsurers[5]->id, 'share_bps' => 10000]);

        // 6. Cat XoL
        $t6 = ReinsuranceTreaty::updateOrCreate(
            ['treaty_code' => 'TRT-2026-0006'],
            [
                'name' => 'Comprehensive Catastrophe XoL 2026',
                'type' => TreatyType::XolCat,
                'line_category' => 'PROPERTY',
                'status' => TreatyStatus::Active,
                'inception_date' => $startDate,
                'expiry_date' => $endDate,
                'attachment_idr' => 200000000,
                'limit_idr' => 500000000,
                'reinstatement_rates_bps' => [10000],
                'layer_premium_idr' => 50000000,
            ]
        );
        $t6->panel()->delete();
        ReinsuranceTreatyPanel::create(['treaty_id' => $t6->id, 'reinsurer_party_id' => $reinsurers[6]->id, 'share_bps' => 5000]);
        ReinsuranceTreatyPanel::create(['treaty_id' => $t6->id, 'reinsurer_party_id' => $reinsurers[7]->id, 'share_bps' => 5000]);
    }

    private function seedParameters(): void
    {
        $params = [
            [
                'code' => 'rbc.correlation',
                'version' => 1,
                'effective_from' => '2026-01-01',
                'source' => 'OJK POJK-71-2016-FIXTURE',
                'simulation_label' => 'S1',
                'value' => [
                    'premium' => ['reserve' => 5000, 'market' => 2500],
                    'reserve' => ['market' => 2500],
                ],
                'is_fixture' => true,
            ],
            [
                'code' => 'rbc.operational_idr',
                'version' => 1,
                'effective_from' => '2026-01-01',
                'source' => 'INTERNAL-ACTUARY-FIXTURE',
                'simulation_label' => 'S1',
                'value' => 30000000,
                'is_fixture' => true,
            ],
            [
                'code' => 'cat.line_peril_map',
                'version' => 1,
                'effective_from' => '2026-01-01',
                'source' => 'BMKG-CAT-CATALOG-FIXTURE',
                'simulation_label' => 'S1',
                'value' => [
                    'PROPERTY' => 'EQ',
                    'VEHICLE' => 'FL',
                    'MARINE_CARGO' => 'FL',
                ],
                'is_fixture' => true,
            ],
        ];

        foreach ($params as $p) {
            InsuranceParameter::updateOrCreate(
                ['code' => $p['code'], 'version' => $p['version']],
                $p
            );
        }
    }

    private function seedCatBond(): void
    {
        $registry = app(CatBondTokenRegistry::class);
        $assetId = $registry->registerBond('RWA-CAT-26A', 10000000000, 1000, 'seed-catbond-26a');

        CatBond::updateOrCreate(
            ['bond_code' => 'CAT-BOND-2026-A'],
            [
                'asset_code' => 'RWA-CAT-26A',
                'rwa_asset_id' => $assetId,
                'principal_idr' => 10000000000,
                'outstanding_principal_idr' => 10000000000,
                'units' => 1000,
                'spread_bps' => 600,
                'attachment_idr' => 2000000000,
                'exhaustion_idr' => 4000000000,
                'status' => CatBondStatus::Active,
                'issue_date' => '2026-01-01',
                'maturity_date' => '2029-01-01',
            ]
        );
    }
}
