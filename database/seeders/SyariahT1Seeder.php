<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Syariah\Domain\Models\SybParameter;
use Modules\Syariah\Ledger\SyariahAccounts;

final class SyariahT1Seeder extends Seeder
{
    /**
     * D162 Simulasi Skala T1:
     * - 20 Sukuk facilities
     * - 2.000 Ijarah facilities & schedules
     * - 5.000 Wealth portfolios & screening orders
     * - 30.000 Muzakki zakat assessments
     * - Audit dua sumber seimbang (exit 0)
     */
    public function run(): void
    {
        $now = '2026-11-06 00:00:00';
        SyariahAccounts::ensureAccountsExist();

        // 0. Parameter S1
        SybParameter::updateOrCreate(
            ['code' => 'GOLD_PRICE_PER_GRAM_IDR', 'version' => 1],
            [
                'effective_from' => '2026-01-01',
                'source' => 'ANTAM_FIXTURE_DSN_MUI',
                'simulation_label' => 'S1',
                'value' => json_encode(['price_idr' => 1_500_000]),
                'is_fixture' => true,
                'owner_confirmed_at' => $now,
            ]
        );

        SybParameter::updateOrCreate(
            ['code' => 'SHARIAH_SCREENING_RATIOS', 'version' => 1],
            [
                'effective_from' => '2026-01-01',
                'source' => 'OJK_POJK_35_2017',
                'simulation_label' => 'S1',
                'value' => json_encode([
                    'max_debt_to_assets_bps' => 4500,
                    'max_non_halal_revenue_bps' => 1000,
                ]),
                'is_fixture' => true,
                'owner_confirmed_at' => $now,
            ]
        );

        $officer = User::firstOrCreate(
            ['email' => 't1-syb-officer@superweb.test'],
            ['name' => 'T1 Syariah Officer', 'role' => 'syariah_officer', 'password' => bcrypt('secret')]
        );

        $dps = User::firstOrCreate(
            ['email' => 't1-syb-dps@superweb.test'],
            ['name' => 'T1 DPS Reviewer', 'role' => 'dps_reviewer', 'password' => bcrypt('secret')]
        );

        $customer = User::firstOrCreate(
            ['email' => 't1-syb-nasabah@superweb.test'],
            ['name' => 'T1 Nasabah Syariah', 'role' => 'customer', 'password' => bcrypt('secret')]
        );

        // 1. Sukuk: 20 Fasilitas & Distribusi Kupon
        if (! DB::table('syb_sukuk_facilities')->where('sukuk_code', 'like', 'SKK-T1-%')->exists()) {
            $sukukList = [];
            $distList = [];

            for ($i = 1; $i <= 20; $i++) {
                $code = sprintf('SKK-T1-%02d', $i);
                $faceValue = 10_000_000_000;
                $couponBps = 750 + ($i * 10);
                $totalUnits = 10_000;

                $dist1 = 187_500_000;
                $dist2 = 187_500_000;
                $totalDist = $dist1 + $dist2;

                $sukukList[] = [
                    'id' => $i,
                    'sukuk_code' => $code,
                    'facility_name' => "Sukuk Ijarah Korporasi T1 #{$i}",
                    'asset_code' => "RWA-ASSET-{$i}",
                    'rwa_asset_id' => null,
                    'face_value_idr' => $faceValue,
                    'total_units' => $totalUnits,
                    'maturity_date' => '2031-12-31',
                    'annual_coupon_bps' => $couponBps,
                    'status' => 'ISSUED',
                    'spv_party_name' => 'PT SPV Amanah Syariah',
                    'dps_reviewer_user_id' => $dps->id,
                    'dps_reviewed_at' => $now,
                    'dps_opinion_note' => 'Akad Sukuk Ijarah disetujui penuh oleh DPS',
                    'issued_at' => $now,
                    'redeemed_at' => null,
                    'total_coupon_distributed_idr' => $totalDist,
                    'idempotency_key' => "idemp:t1:skk:{$i}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $distList[] = [
                    'sukuk_facility_id' => $i,
                    'period_code' => '2026-Q1',
                    'coupon_pool_idr' => $dist1,
                    'distributed_idr' => $dist1,
                    'rounding_reserve_idr' => 0,
                    'distribution_date' => '2026-03-31',
                    'idempotency_key' => "idemp:t1:dist:{$i}:q1",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $distList[] = [
                    'sukuk_facility_id' => $i,
                    'period_code' => '2026-Q2',
                    'coupon_pool_idr' => $dist2,
                    'distributed_idr' => $dist2,
                    'rounding_reserve_idr' => 0,
                    'distribution_date' => '2026-06-30',
                    'idempotency_key' => "idemp:t1:dist:{$i}:q2",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('syb_sukuk_facilities')->insert($sukukList);
            DB::table('syb_sukuk_distributions')->insert($distList);
        }

        // 2. Ijarah: 2.000 Fasilitas & Jadwal (Chunked)
        if (! DB::table('syb_ijarah_facilities')->where('contract_code', 'like', 'IJR-T1-%')->exists()) {
            $ijarahBatch = [];
            $schedulesBatch = [];

            for ($i = 1; $i <= 2000; $i++) {
                $code = sprintf('IJR-T1-%04d', $i);
                $cost = 120_000_000;
                $tenor = 12;
                $ujrahTotal = 132_000_000;
                $ujrahPerMonth = 11_000_000;
                $deprecPerMonth = 10_000_000;

                $paidUjrah = 11_000_000;
                $accumDeprec = 10_000_000;
                $carryingBook = $cost - $accumDeprec;

                $ijarahBatch[] = [
                    'id' => $i,
                    'contract_code' => $code,
                    'customer_user_id' => $customer->id,
                    'asset_name' => "Mesin Otomasi Industri Halal T1 #{$i}",
                    'asset_cost_idr' => $cost,
                    'tenor_months' => $tenor,
                    'total_ujrah_idr' => $ujrahTotal,
                    'residual_option_price_idr' => 1_000_000,
                    'status' => 'ACTIVE',
                    'dps_reviewer_user_id' => $dps->id,
                    'dps_reviewed_at' => $now,
                    'dps_opinion_note' => 'Akad sewa IMBT disetujui',
                    'activated_at' => $now,
                    'option_exercised_at' => null,
                    'ownership_transferred_at' => null,
                    'total_ujrah_paid_idr' => $paidUjrah,
                    'accumulated_depreciation_idr' => $accumDeprec,
                    'carrying_book_value_idr' => $carryingBook,
                    'total_tazir_idr' => 0,
                    'idempotency_key' => "idemp:t1:ijr:{$i}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $schedulesBatch[] = [
                    'facility_id' => $i,
                    'period' => 1,
                    'due_date' => '2026-02-01',
                    'ujrah_idr' => $ujrahPerMonth,
                    'depreciation_idr' => $deprecPerMonth,
                    'accumulated_depreciation_idr' => $deprecPerMonth,
                    'carrying_book_value_idr' => $cost - $deprecPerMonth,
                    'paid_amount_idr' => $ujrahPerMonth,
                    'status' => 'PAID',
                    'paid_at' => $now,
                    'payment_idempotency_key' => "idemp:t1:ijr:pay:{$i}:1",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $schedulesBatch[] = [
                    'facility_id' => $i,
                    'period' => 2,
                    'due_date' => '2026-03-01',
                    'ujrah_idr' => $ujrahPerMonth,
                    'depreciation_idr' => $deprecPerMonth,
                    'accumulated_depreciation_idr' => $deprecPerMonth * 2,
                    'carrying_book_value_idr' => $cost - ($deprecPerMonth * 2),
                    'paid_amount_idr' => 0,
                    'status' => 'UNPAID',
                    'paid_at' => null,
                    'payment_idempotency_key' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($ijarahBatch) >= 500) {
                    DB::table('syb_ijarah_facilities')->insert($ijarahBatch);
                    DB::table('syb_ijarah_facility_schedules')->insert($schedulesBatch);
                    $ijarahBatch = [];
                    $schedulesBatch = [];
                }
            }

            if (! empty($ijarahBatch)) {
                DB::table('syb_ijarah_facilities')->insert($ijarahBatch);
                DB::table('syb_ijarah_facility_schedules')->insert($schedulesBatch);
            }
        }

        // 3. Wealth: 5.000 Portofolio & Transaksi Instrumen Syariah
        if (! DB::table('syb_wealth_instruments')->where('ticker_symbol', 'HALAL-CORP')->exists()) {
            DB::table('syb_wealth_instruments')->insert([
                [
                    'id' => 1,
                    'ticker_symbol' => 'HALAL-CORP',
                    'name' => 'PT Amanah Industri Halal Tbk',
                    'instrument_type' => 'SHARIAH_EQUITY',
                    'interest_bearing_debt_idr' => 2_000_000_000,
                    'total_assets_idr' => 10_000_000_000,
                    'debt_ratio_bps' => 2000,
                    'non_halal_revenue_idr' => 100_000_000,
                    'total_revenue_idr' => 10_000_000_000,
                    'non_halal_revenue_ratio_bps' => 100,
                    'is_shariah_compliant' => true,
                    'last_screened_at' => $now,
                    'sector_code' => 'CONSUMER_GOODS',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        if (! DB::table('syb_wealth_portfolios')->where('user_id', $customer->id)->exists()) {
            $ports = [];
            $orders = [];
            for ($p = 1; $p <= 5000; $p++) {
                $ports[] = [
                    'id' => $p,
                    'user_id' => $customer->id,
                    'risk_profile' => $p % 2 === 0 ? 'MODERATE' : 'CONSERVATIVE',
                    'current_balance_idr' => 15_000_000,
                    'total_invested_idr' => 10_000_000,
                    'total_purification_idr' => 100_000,
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $orders[] = [
                    'portfolio_id' => $p,
                    'instrument_id' => 1,
                    'order_type' => 'BUY',
                    'units' => 100,
                    'amount_idr' => 10_000_000,
                    'status' => 'EXECUTED',
                    'idempotency_key' => "idemp:t1:ord:{$p}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($ports) >= 1000) {
                    DB::table('syb_wealth_portfolios')->insert($ports);
                    DB::table('syb_wealth_orders')->insert($orders);
                    $ports = [];
                    $orders = [];
                }
            }

            if (! empty($ports)) {
                DB::table('syb_wealth_portfolios')->insert($ports);
                DB::table('syb_wealth_orders')->insert($orders);
            }
        }

        // 4. Zakat: 30.000 Muzakki Penilaian Haul (Chunked 2.000)
        if (! DB::table('syb_zakat_assessments')->where('customer_user_id', $customer->id)->exists()) {
            $zakatBatch = [];
            $goldPrice = 1_500_000;
            $nisabIdr = 85 * $goldPrice;

            for ($z = 1; $z <= 30000; $z++) {
                $wealth = 200_000_000 + ($z * 10_000);
                $debt = 20_000_000;
                $netBase = $wealth - $debt;
                $zakatAmt = (int) round($netBase * 0.025);

                $zakatBatch[] = [
                    'customer_user_id' => $customer->id,
                    'haul_year' => 1000 + $z,
                    'gold_price_per_gram_idr' => $goldPrice,
                    'nisab_idr' => $nisabIdr,
                    'qualifying_wealth_idr' => $wealth,
                    'due_debts_idr' => $debt,
                    'net_zakat_base_idr' => $netBase,
                    'holding_days' => 354,
                    'haul_fulfilled' => true,
                    'reaches_nisab' => true,
                    'is_obligated' => true,
                    'zakat_amount_idr' => $zakatAmt,
                    'is_collected' => true,
                    'collected_at' => $now,
                    'auto_debit' => true,
                    'certificate_number' => sprintf('CERT-ZKT-T1-%06d', $z),
                    'idempotency_key' => "idemp:t1:zkt:{$z}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($zakatBatch) >= 2000) {
                    DB::table('syb_zakat_assessments')->insert($zakatBatch);
                    $zakatBatch = [];
                }
            }

            if (! empty($zakatBatch)) {
                DB::table('syb_zakat_assessments')->insert($zakatBatch);
            }
        }
    }
}
