<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Insurance\Domain\Enums\CatBondStatus;
use Modules\Insurance\Domain\Enums\TreatyStatus;
use Modules\Insurance\Domain\Enums\TreatyType;
use Modules\Insurance\Domain\Models\AgriInsuranceClaim;
use Modules\Insurance\Domain\Models\AgriInsurancePolicy;
use Modules\Insurance\Domain\Models\CatBond;
use Modules\Insurance\Domain\Models\EmbedProduct;
use Modules\Insurance\Domain\Models\HealthPolicy;
use Modules\Insurance\Domain\Models\InsuranceActuarialTriangle;
use Modules\Insurance\Domain\Models\InsuranceClaimPenuh;
use Modules\Insurance\Domain\Models\InsuranceCommandSnapshot;
use Modules\Insurance\Domain\Models\InsuranceGrievanceTicket;
use Modules\Insurance\Domain\Models\InsuranceParameter;
use Modules\Insurance\Domain\Models\InsurancePolicyPenuh;
use Modules\Insurance\Domain\Models\InsuranceProductPenuh;
use Modules\Insurance\Domain\Models\LifeBeneficiary;
use Modules\Insurance\Domain\Models\LifePolicy;
use Modules\Insurance\Domain\Models\MarketBroker;
use Modules\Insurance\Domain\Models\MicroInsurancePolicy;
use Modules\Insurance\Domain\Models\ParametricTrigger;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;
use Modules\Insurance\Domain\Models\ReinsuranceTreatyPanel;
use Modules\Insurance\Domain\Models\TakafulFund;
use Modules\Insurance\Domain\Models\TakafulParticipant;
use Modules\Insurance\Domain\Models\UnitLinkFund;
use Modules\Insurance\Domain\Models\UnitLinkHolding;
use Modules\Party\Domain\Enums\PartyStatus;
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
        InsuranceClaimPenuh::where('idempotency_key', 'like', 't1:clm:%')->delete();
        InsurancePolicyPenuh::where('policy_number', 'like', 'POL-T1-%')->delete();

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

        // 9. Embedded Insurance & Broker Marketplace (Fase 158)
        $this->seedEmbeddedAndBrokers();

        // 10. Life, Health & Wellness Insurance (Fase 159)
        $this->seedLifeHealthWellness();

        // 11. Takaful, Agri-Insurance & Command Center (Fase 160)
        $this->seedTakafulAgriOps();
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
                    'status' => PartyStatus::Verified,
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

    private function seedEmbeddedAndBrokers(): void
    {
        $now = now();

        // 1. Embedded Products
        $embedProducts = [
            [
                'code' => 'EMB-FLIGHT-01',
                'name' => 'Automated Flight Delay Guarantee',
                'line_domain' => 'AVI',
                'coverage_type' => 'DELAY',
                'default_sum_insured_idr' => 5000000,
                'premium_rate_bps' => 200,
                'min_premium_idr' => 100000,
                'is_active' => true,
            ],
            [
                'code' => 'EMB-GADGET-01',
                'name' => 'E-Commerce Gadget Screen Shield',
                'line_domain' => 'MALL',
                'coverage_type' => 'DAMAGE',
                'default_sum_insured_idr' => 3000000,
                'premium_rate_bps' => 150,
                'min_premium_idr' => 50000,
                'is_active' => true,
            ],
            [
                'code' => 'EMB-AGRI-01',
                'name' => 'Micro-Crop Parametric Drought Shield',
                'line_domain' => 'AGRI',
                'coverage_type' => 'PARAMETRIC',
                'default_sum_insured_idr' => 10000000,
                'premium_rate_bps' => 300,
                'min_premium_idr' => 250000,
                'is_active' => true,
            ],
        ];

        foreach ($embedProducts as $pData) {
            EmbedProduct::updateOrCreate(['code' => $pData['code']], $pData);
        }

        // 2. Parametric Triggers
        $triggers = [
            [
                'trigger_code' => 'TRG-NDVI-PLOT-01',
                'metric_name' => 'NDVI',
                'region_or_plot' => 'PLOT-WEST-JAVA-001',
                'threshold_value' => 350,
                'comparison_operator' => '<',
                'payout_percentage_bps' => 10000,
                'is_active' => true,
            ],
            [
                'trigger_code' => 'TRG-FLIGHT-DELAY-120',
                'metric_name' => 'FLIGHT_DELAY_MINUTES',
                'region_or_plot' => 'CGK-DPS',
                'threshold_value' => 120,
                'comparison_operator' => '>=',
                'payout_percentage_bps' => 5000,
                'is_active' => true,
            ],
        ];

        foreach ($triggers as $tData) {
            ParametricTrigger::updateOrCreate(['trigger_code' => $tData['trigger_code']], $tData);
        }

        // 3. Broker Parties & Profiles
        $brokerParties = [
            'PT Pialang Asuransi Digital Mandiri' => 'BROKER-LIC-001',
            'PT Proteksi Finansial Terpadu' => 'BROKER-LIC-002',
        ];

        foreach ($brokerParties as $bName => $bLic) {
            $party = Party::firstOrCreate(
                ['name' => $bName],
                [
                    'name_normalized' => strtolower(trim($bName)),
                    'type' => PartyType::Company,
                    'short_name' => $bLic,
                    'status' => PartyStatus::Verified,
                    'is_active' => true,
                ]
            );

            MarketBroker::updateOrCreate(
                ['license_number' => $bLic],
                [
                    'party_id' => $party->id,
                    'tier' => 'gold',
                    'commission_rate_bps' => 1500,
                    'rating_score' => 92,
                    'status' => 'active',
                ]
            );
        }
    }

    private function seedLifeHealthWellness(): void
    {
        $now = now();

        // 1. Dana Investasi Unit Link
        $funds = [
            [
                'fund_code' => 'UL-EQ-AGGRESSIVE',
                'name' => 'Nusantara Aggressive Equity Fund',
                'asset_class' => 'EQUITY',
                'current_nav_per_unit_idr' => 1750,
                'total_aum_idr' => 17500000000,
                'total_units' => 10000000,
                'management_fee_bps' => 150,
                'is_active' => true,
            ],
            [
                'fund_code' => 'UL-BALANCED-GROWTH',
                'name' => 'Nusantara Balanced Dynamic Fund',
                'asset_class' => 'BALANCED',
                'current_nav_per_unit_idr' => 1400,
                'total_aum_idr' => 14000000000,
                'total_units' => 10000000,
                'management_fee_bps' => 125,
                'is_active' => true,
            ],
            [
                'fund_code' => 'UL-FIXED-SECURE',
                'name' => 'Nusantara Sovereign Bond Fund',
                'asset_class' => 'FIXED_INCOME',
                'current_nav_per_unit_idr' => 1150,
                'total_aum_idr' => 11500000000,
                'total_units' => 10000000,
                'management_fee_bps' => 100,
                'is_active' => true,
            ],
        ];

        foreach ($funds as $fData) {
            UnitLinkFund::updateOrCreate(['fund_code' => $fData['fund_code']], $fData);
        }

        // 2. Polis Asuransi Kesehatan
        HealthPolicy::updateOrCreate(
            ['policy_number' => 'POL-HLTH-PLATINUM-001'],
            [
                'member_party_id' => '00000000-0000-0000-0000-000000000001',
                'plan_tier' => 'PLATINUM',
                'annual_limit_idr' => 500000000,
                'annual_utilized_idr' => 0,
                'room_daily_limit_idr' => 2000000,
                'deductible_idr' => 0,
                'status' => 'active',
                'effective_date' => $now->toDateString(),
                'expiry_date' => $now->copy()->addYear()->toDateString(),
            ]
        );

        // 3. Polis Asuransi Jiwa
        $lifePolicy = LifePolicy::updateOrCreate(
            ['policy_number' => 'POL-LIFE-TERM-001'],
            [
                'insured_party_id' => '00000000-0000-0000-0000-000000000001',
                'plan_type' => 'TERM_LIFE',
                'entry_age' => 35,
                'term_years' => 20,
                'sum_insured_idr' => 1000000000,
                'annual_premium_idr' => 3500000,
                'saving_accumulated_idr' => 0,
                'status' => 'active',
                'issued_date' => $now->toDateString(),
                'maturity_date' => $now->copy()->addYears(20)->toDateString(),
            ]
        );

        LifeBeneficiary::updateOrCreate(
            ['life_policy_id' => $lifePolicy->id, 'beneficiary_party_id' => '00000000-0000-0000-0000-000000000002'],
            [
                'relationship' => 'SPOUSE',
                'share_percentage_bps' => 10000,
                'is_primary' => true,
            ]
        );

        // Seed initial holdings for funds
        foreach ($funds as $fData) {
            $fund = UnitLinkFund::where('fund_code', $fData['fund_code'])->first();
            if ($fund) {
                UnitLinkHolding::updateOrCreate(
                    ['life_policy_id' => $lifePolicy->id, 'fund_id' => $fund->id],
                    [
                        'units_held' => $fund->total_units,
                        'acquisition_cost_idr' => $fund->total_aum_idr,
                        'current_valuation_idr' => $fund->total_aum_idr,
                    ]
                );
            }
        }
    }

    private function seedTakafulAgriOps(): void
    {
        $now = now();

        // 1. Dana Takaful
        $fund = TakafulFund::updateOrCreate(
            ['fund_code' => 'TK-TABARRU-NATIONAL'],
            [
                'fund_type' => 'TABARRU',
                'name' => 'Dana Kebajikan Tabarru Nasional',
                'balance_idr' => 15000000000,
                'total_contributions_idr' => 20000000000,
                'total_claims_paid_idr' => 5000000000,
                'contingency_reserve_idr' => 2500000000,
                'is_active' => true,
            ]
        );

        TakafulParticipant::updateOrCreate(
            ['policy_number' => 'POL-TK-FAMILY-001'],
            [
                'participant_party_id' => '00000000-0000-0000-0000-000000000001',
                'fund_id' => $fund->id,
                'plan_type' => 'FAMILY_TAKAFUL',
                'contribution_annual_idr' => 12000000,
                'tabarru_portion_bps' => 7000,
                'ujrah_portion_bps' => 3000,
                'sum_covered_idr' => 250000000,
                'status' => 'active',
                'effective_date' => $now->toDateString(),
                'expiry_date' => $now->copy()->addYear()->toDateString(),
            ]
        );

        // 2. Asuransi Pertanian Berbasis Indeks
        $agriPolicy = AgriInsurancePolicy::updateOrCreate(
            ['policy_number' => 'POL-AGRI-PADI-IND-01'],
            [
                'farmer_party_id' => '00000000-0000-0000-0000-000000000003',
                'commodity' => 'PADI',
                'farm_area_ha' => 10,
                'sum_insured_idr' => 50000000,
                'premium_annual_idr' => 1500000,
                'index_type' => 'RAINFALL',
                'trigger_threshold_val' => 350,
                'status' => 'active',
                'effective_date' => $now->toDateString(),
                'expiry_date' => $now->copy()->addYear()->toDateString(),
            ]
        );

        AgriInsuranceClaim::updateOrCreate(
            ['claim_number' => 'CLM-AGRI-FLOOD-001'],
            [
                'policy_id' => $agriPolicy->id,
                'actual_index_val' => 410,
                'gross_claim_idr' => 50000000,
                'loan_offset_amount_idr' => 20000000,
                'net_farmer_payout_idr' => 30000000,
                'status' => 'settled',
                'adjudicated_at' => $now,
            ]
        );

        // 3. Asuransi Mikro Massal
        MicroInsurancePolicy::updateOrCreate(
            ['policy_number' => 'POL-MICRO-TRANSIT-SEED'],
            [
                'member_party_id' => '00000000-0000-0000-0000-000000000004',
                'product_type' => 'TRANSIT_DAILY',
                'daily_premium_idr' => 5000,
                'sum_insured_idr' => 25000000,
                'duration_days' => 7,
                'status' => 'active',
                'issued_at' => $now,
                'expires_at' => $now->copy()->addDays(7),
            ]
        );

        // 4. Command Center Snapshot
        InsuranceCommandSnapshot::updateOrCreate(
            ['snapshot_date' => $now->toDateString()],
            [
                'gross_written_premium_idr' => 450000000000,
                'net_earned_premium_idr' => 382500000000,
                'claims_incurred_idr' => 210375000000,
                'loss_ratio_bps' => 5500,
                'solvency_rbc_bps' => 24500,
                'total_technical_reserve_idr' => 292500000000,
                'reinsurance_recoverable_idr' => 42075000000,
            ]
        );

        // 5. Keluhan Nasabah & Kepatuhan Regulasi
        InsuranceGrievanceTicket::updateOrCreate(
            ['ticket_number' => 'TCK-OJK-2026-001'],
            [
                'policy_ref' => 'POL-LIFE-TERM-001',
                'complainant_party_id' => '00000000-0000-0000-0000-000000000002',
                'category' => 'CLAIM_DENIAL',
                'grievance_text' => 'Permintaan peninjauan ulang klaim rawat inap tindakan darurat sesuai rekomendasi dokter spesialis.',
                'sla_deadline' => $now->copy()->addDays(14)->toDateString(),
                'status' => 'in_investigation',
            ]
        );
    }
}
