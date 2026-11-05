<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Syariah\Domain\Models\SybSocialImpactMetric;
use Modules\Syariah\Ledger\SyariahAccounts;

class SyariahMicroT1Seeder extends Seeder
{
    public function run(): void
    {
        SyariahAccounts::ensureAccountsExist();

        $now = CarbonImmutable::now();

        // 1. Seed 10 BMT / Koperasi Syariah
        $coopCodes = [];
        $coopInserts = [];
        for ($i = 1; $i <= 10; $i++) {
            $code = sprintf('BMT-COOP-%03d', $i);
            $coopCodes[] = $code;
            $coopInserts[] = [
                'cooperative_code' => $code,
                'name' => "BMT Syariah Amanah Mandiri #{$i}",
                'legal_entity_number' => sprintf('AHU-%06d.AH.01.26', $i * 1000 + 42),
                'city' => ['Surabaya', 'Malang', 'Sidoarjo', 'Gresik', 'Jember'][$i % 5],
                'address' => "Jl. Koperasi Syariah No. {$i}, Jawa Timur",
                'dps_advisor' => "Dr. KH. Ahmad Shariah DPS #{$i}",
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('syb_bmt_cooperatives')->insertOrIgnore($coopInserts);

        // 2. Seed 100 Rekening Simpanan Wadiah & Mudharabah
        $savingsInserts = [];
        for ($s = 1; $s <= 100; $s++) {
            $coop = $coopCodes[$s % 10];
            $savingsInserts[] = [
                'account_number' => sprintf('SAV-BMT-%06d', $s),
                'cooperative_code' => $coop,
                'customer_party_ref' => sprintf('PARTY-CUST-%05d', $s),
                'contract_type' => ($s % 2 === 0) ? 'WADIAH_YAD_DHAMANAH' : 'MUDHARABAH_MUTLAQAH',
                'balance_idr' => 250000000 + ($s * 1000000), // Rp 2.5jt - Rp 3.5jt
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('syb_bmt_savings')->insertOrIgnore($savingsInserts);

        // 3. Seed 20 Kelompok Tanggung Renteng (100 Anggota)
        for ($g = 1; $g <= 20; $g++) {
            $gCode = sprintf('GRP-BMT-%04d', $g);
            DB::table('syb_group_financings')->insertOrIgnore([
                'group_code' => $gCode,
                'cooperative_code' => $coopCodes[$g % 10],
                'group_name' => "Kelompok Mawar Berkah #{$g}",
                'leader_party_ref' => sprintf('PARTY-LEAD-%04d', $g),
                'total_members' => 5,
                'total_facility_amount_idr' => 2500000000, // Rp 25.000.000
                'outstanding_balance_idr' => 2000000000,
                'shariah_contract' => 'MUDHARABAH_BERTINGKAT',
                'profit_nisbah_shohibul_maal_bps' => 4000,
                'group_liability_active' => true,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $memberInserts = [];
            for ($m = 1; $m <= 5; $m++) {
                $memberInserts[] = [
                    'group_code' => $gCode,
                    'member_party_ref' => sprintf('MBR-%s-%02d', $gCode, $m),
                    'member_name' => "Ibu Pelaku Usaha #{$g}-{$m}",
                    'share_amount_idr' => 500000000, // Rp 5.000.000
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('syb_group_members')->insertOrIgnore($memberInserts);
        }

        // 4. Seed 50 Fasilitas Gig Worker Microcredit
        $gigInserts = [];
        for ($w = 1; $w <= 50; $w++) {
            $gigInserts[] = [
                'loan_code' => sprintf('GIG-LN-%05d', $w),
                'worker_party_ref' => sprintf('WRK-GIG-%05d', $w),
                'gross_average_income_idr' => 450000000, // Rp 4.5jt
                'income_volatility_bps' => 1800,
                'amanah_score' => 85,
                'approved_credit_limit_idr' => 1500000000, // Rp 15jt
                'disbursed_amount_idr' => 500000000, // Rp 5jt
                'remaining_balance_idr' => 350000000,
                'monthly_deduction_idr' => 100000000,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('syb_gig_microloans')->insertOrIgnore($gigInserts);

        // 5. Seed 20 Fasilitas Salam Pertanian Terikat NDVI
        for ($a = 1; $a <= 20; $a++) {
            $fCode = sprintf('AGR-SLM-%04d', $a);
            DB::table('syb_agri_micro_facilities')->insertOrIgnore([
                'facility_code' => $fCode,
                'farmer_party_ref' => sprintf('FRM-PLAS-%04d', $a),
                'plot_code' => sprintf('PLOT-SAWAH-%03d', $a),
                'commodity' => 'Padi Organik Ciherang',
                'contract_type' => 'SALAM',
                'financing_amount_idr' => 1000000000, // Rp 10.000.000
                'outstanding_balance_idr' => 1000000000,
                'expected_yield_kg' => 2500,
                'contract_price_per_kg_idr' => 400000, // Rp 4.000/kg
                'milestone_count' => 3,
                'disbursed_amount_idr' => 400000000,
                'ndvi_min_threshold_bps' => 3000,
                'current_ndvi_bps' => 4800,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('syb_agri_milestones')->insertOrIgnore([
                [
                    'milestone_code' => "MLS-{$fCode}-01",
                    'facility_code' => $fCode,
                    'milestone_number' => 1,
                    'title' => 'Tahap Pengolahan Lahan & Bibit Unggul',
                    'target_ndvi_bps' => 2500,
                    'disbursement_percentage' => 40,
                    'amount_idr' => 400000000,
                    'verified_at' => $now,
                    'status' => 'DISBURSED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'milestone_code' => "MLS-{$fCode}-02",
                    'facility_code' => $fCode,
                    'milestone_number' => 2,
                    'title' => 'Tahap Pemupukan Berimbang & Vegetatif Aktif',
                    'target_ndvi_bps' => 4500,
                    'disbursement_percentage' => 30,
                    'amount_idr' => 300000000,
                    'verified_at' => null,
                    'status' => 'PENDING',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'milestone_code' => "MLS-{$fCode}-03",
                    'facility_code' => $fCode,
                    'milestone_number' => 3,
                    'title' => 'Tahap Pematangan Bulir & Panen Raya',
                    'target_ndvi_bps' => 6000,
                    'disbursement_percentage' => 30,
                    'amount_idr' => 300000000,
                    'verified_at' => null,
                    'status' => 'PENDING',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // 6. Seed Metrik Dampak Sosial Tahunan
        SybSocialImpactMetric::updateOrCreate(
            ['period_year' => 2026],
            [
                'total_beneficiaries' => 170,
                'jobs_created' => 255,
                'mustahik_to_muzakki_count' => 34,
                'avg_income_increase_bps' => 3200,
                'micro_npf_ratio_bps' => 110,
                'notes' => 'Simulasi deterministik skala T1 pemberdayaan ekonomi syariah.',
            ]
        );

        // 7. Seed Restrukturisasi & Graduasi Mustahik (T1)
        for ($r = 1; $r <= 5; $r++) {
            DB::table('syb_micro_restructurings')->insertOrIgnore([
                'restruct_code' => sprintf('RST-T1-%04d', $r),
                'facility_code' => sprintf('GRP-BMT-%04d', $r),
                'reason' => 'Penyesuaian dampak siklus musim panen dan perpanjangan tenor usaha mikro',
                'loss_type' => 'NON_NEGLIGENCE_LOSS',
                'previous_nisbah_bps' => 4000,
                'new_nisbah_bps' => 3500,
                'previous_tenor_months' => 12,
                'new_tenor_months' => 18,
                'restructured_principal_idr' => 1500000000,
                'moral_hazard_detected' => false,
                'approved_by' => 'DPS-SUPERVISOR-T1',
                'approved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        for ($g = 1; $g <= 10; $g++) {
            DB::table('syb_mustahik_graduations')->insertOrIgnore([
                'graduation_code' => sprintf('GRD-T1-%04d', $g),
                'party_ref' => sprintf('PARTY-MUSTAHIK-%04d', $g),
                'current_stage' => 'MIKRO_BERTUMBUH',
                'target_stage' => 'MUZAKKI_MANDIRI',
                'omzet_baseline_idr' => 1500000000, // Rp 15jt
                'omzet_current_idr' => 2500000000,  // Rp 25jt (+66%)
                'growth_rate_bps' => 6667,
                'field_officer_verifier' => 'OFFICER-FIELD-01',
                'amil_supervisor_verifier' => 'SUPERVISOR-AMIL-01',
                'verified_at' => $now,
                'status' => 'GRADUATED',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
