<?php

declare(strict_types=1);

namespace Modules\Intercompany\database\seeders;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Intercompany\Domain\Models\CostSharingAgreement;
use Modules\Intercompany\Domain\Models\CostSharingAllocation;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyLoanSchedule;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\IntercompanyTransactionHistory;
use Modules\Intercompany\Domain\Models\NciDividend;
use Modules\Intercompany\Domain\Models\SubsidiaryNci;
use Modules\Intercompany\Domain\Models\TransferPricingBenchmark;
use Modules\Intercompany\Domain\Models\TransferPricingRule;

/**
 * Seeder T1 Modul Intercompany & Keuangan Grup (W8-P5 / Fase 52)
 *
 * Dataset deterministik skala enterprise:
 * - >= 6 Entitas Legal Grup (Holding, 4 Anak Domestik, 1 Anak Luar Negeri SG)
 * - >= 25 Aturan Penetapan Harga Transfer (TP Rules) & Benchmarks IQR
 * - >= 120 Transaksi Cermin Bilateral (ic_transactions) mencakup ke-8 status
 * - >= 15 Fasilitas Pinjaman Intercompany (ic_loans) dengan skedul amortisasi D6
 * - >= 50 Jurnal Eliminasi Konsolidasi Bilateral (ic_elimination_entries)
 * - >= 8 Catatan NCI & Dividen Kepentingan Non-Pengendali (ic_subsidiary_nci & ic_nci_dividends)
 * - Perjanjian Cost Sharing (CSA) & Transfer Aset/Persediaan
 * - 15 Kasus Bernama (Named Test Cases) terindeks
 */
class IntercompanyT1Seeder extends Seeder
{
    public const BASE_DATE = '2026-10-01';

    public function run(): void
    {
        $this->call(LedgerAccountsSeeder::class);

        $base = CarbonImmutable::parse(self::BASE_DATE);

        // 1. Bersihkan tabel Intercompany secara aman & idempoten
        DB::table('ic_nci_dividends')->delete();
        DB::table('ic_subsidiary_nci')->delete();
        DB::table('ic_unrealized_inventory_profits')->delete();
        DB::table('ic_elimination_entries')->delete();
        DB::table('ic_cost_sharing_allocations')->delete();
        DB::table('ic_cost_sharing_agreements')->delete();
        DB::table('ic_stock_transfers')->delete();
        DB::table('ic_asset_transfers')->delete();
        DB::table('ic_tp_documents')->delete();
        DB::table('ic_tp_true_ups')->delete();
        DB::table('ic_loan_schedules')->delete();
        DB::table('ic_loans')->delete();
        DB::table('ic_transaction_histories')->delete();
        DB::table('ic_transactions')->delete();
        DB::table('ic_tp_benchmarks')->delete();
        DB::table('ic_transfer_pricing_rules')->delete();
        DB::table('ic_entities')->delete();

        // 2. Registrasi >= 6 Entitas Legal Grup
        $entitiesDef = [
            [
                'code' => 'HOLDING',
                'name' => 'PT Konglomerasi Nusantara Utama',
                'country' => 'ID',
                'currency' => 'IDR',
                'tax_id' => '01.111.111.1-111.000',
                'ownership_permille' => 100000,
                'is_parent' => true,
            ],
            [
                'code' => 'SUB_A_MANUF',
                'name' => 'PT Manufaktur Komponen Presisi',
                'country' => 'ID',
                'currency' => 'IDR',
                'tax_id' => '02.222.222.2-222.000',
                'ownership_permille' => 100000,
                'is_parent' => false,
            ],
            [
                'code' => 'SUB_B_DIST',
                'name' => 'PT Distribusi Logistik Niaga',
                'country' => 'ID',
                'currency' => 'IDR',
                'tax_id' => '03.333.333.3-333.000',
                'ownership_permille' => 80000, // 80% Induk, 20% NCI
                'is_parent' => false,
            ],
            [
                'code' => 'SUB_C_TECH',
                'name' => 'PT Solusi Teknologi Digital',
                'country' => 'ID',
                'currency' => 'IDR',
                'tax_id' => '04.444.444.4-444.000',
                'ownership_permille' => 90000, // 90% Induk, 10% NCI
                'is_parent' => false,
            ],
            [
                'code' => 'SUB_D_LOGIST',
                'name' => 'PT Armada Logistik Nusantara',
                'country' => 'ID',
                'currency' => 'IDR',
                'tax_id' => '05.555.555.5-555.000',
                'ownership_permille' => 70000, // 70% Induk, 30% NCI
                'is_parent' => false,
            ],
            [
                'code' => 'SUB_E_OVERSEAS_SG',
                'name' => 'Nusantara Global Trading Pte Ltd',
                'country' => 'SG',
                'currency' => 'SGD',
                'tax_id' => '202611999G',
                'ownership_permille' => 100000,
                'is_parent' => false,
            ],
        ];

        $entityModels = [];
        foreach ($entitiesDef as $e) {
            $entityModels[$e['code']] = IntercompanyEntity::create([
                'entity_code' => $e['code'],
                'legal_name' => $e['name'],
                'country_code' => $e['country'],
                'functional_currency' => $e['currency'],
                'tax_id_npwp' => $e['tax_id'],
                'ownership_percent_permille' => $e['ownership_permille'],
                'is_parent' => $e['is_parent'],
                'is_active' => true,
            ]);
        }

        // 3. Registrasi >= 25 Aturan Transfer Pricing (TP Rules) & Benchmarks
        $tpCategories = [
            ['code' => 'TPR-AUTO-01', 'cat' => 'Otomotif & Komponen Mesin', 'method' => 'CPM', 'min' => 600, 'med' => 1000, 'max' => 1400],
            ['code' => 'TPR-ELEC-02', 'cat' => 'Komponen Elektronika Mikro', 'method' => 'CPM', 'min' => 800, 'med' => 1200, 'max' => 1600],
            ['code' => 'TPR-PLAS-03', 'cat' => 'Injeksi Plastik & Polimer', 'method' => 'CPM', 'min' => 500, 'med' => 850, 'max' => 1200],
            ['code' => 'TPR-PACK-04', 'cat' => 'Kemasan Industri Corrugated', 'method' => 'CPM', 'min' => 450, 'med' => 750, 'max' => 1100],
            ['code' => 'TPR-STEL-05', 'cat' => 'Fabrikasi Baja & Metalurgi', 'method' => 'CPM', 'min' => 550, 'med' => 900, 'max' => 1350],
            ['code' => 'TPR-PREC-06', 'cat' => 'Peralatan Presisi Dies & Molds', 'method' => 'CPM', 'min' => 1000, 'med' => 1500, 'max' => 2200],
            ['code' => 'TPR-IT-07', 'cat' => 'Pengembangan Software ERP', 'method' => 'TNMM', 'min' => 700, 'med' => 1100, 'max' => 1500],
            ['code' => 'TPR-LEGL-08', 'cat' => 'Jasa Hukum & Kepatuhan Grup', 'method' => 'TNMM', 'min' => 600, 'med' => 950, 'max' => 1300],
            ['code' => 'TPR-FIN-09', 'cat' => 'Jasa Manajemen Keuangan', 'method' => 'TNMM', 'min' => 500, 'med' => 800, 'max' => 1200],
            ['code' => 'TPR-SSC-10', 'cat' => 'Pusat Layanan Bersama HR & GA', 'method' => 'TNMM', 'min' => 400, 'med' => 650, 'max' => 950],
            ['code' => 'TPR-CPO-11', 'cat' => 'Minyak Sawit Mentah (CPO)', 'method' => 'CUP', 'min' => 200, 'med' => 400, 'max' => 600],
            ['code' => 'TPR-COAL-12', 'cat' => 'Batubara Kalori Sedang', 'method' => 'CUP', 'min' => 300, 'med' => 550, 'max' => 800],
            ['code' => 'TPR-RUBR-13', 'cat' => 'Karet Remah SIR-20', 'method' => 'CUP', 'min' => 250, 'med' => 450, 'max' => 700],
            ['code' => 'TPR-COPR-14', 'cat' => 'Katoda Tembaga Grade A', 'method' => 'CUP', 'min' => 150, 'med' => 300, 'max' => 500],
            ['code' => 'TPR-POLY-15', 'cat' => 'Biji Plastik Polipropilena', 'method' => 'CUP', 'min' => 350, 'med' => 600, 'max' => 900],
            ['code' => 'TPR-CE-16', 'cat' => 'Distribusi Elektronik Konsumen', 'method' => 'RPM', 'min' => 400, 'med' => 700, 'max' => 1100],
            ['code' => 'TPR-PHAR-17', 'cat' => 'Distribusi Farmasi & Alkes', 'method' => 'RPM', 'min' => 600, 'med' => 950, 'max' => 1400],
            ['code' => 'TPR-FMCG-18', 'cat' => 'Distribusi Makanan & Minuman', 'method' => 'RPM', 'min' => 350, 'med' => 600, 'max' => 900],
            ['code' => 'TPR-LOG-19', 'cat' => 'Layanan Angkutan Darat & Truk', 'method' => 'CPM', 'min' => 500, 'med' => 850, 'max' => 1250],
            ['code' => 'TPR-WHS-20', 'cat' => 'Jasa Pergudangan & Cold Storage', 'method' => 'CPM', 'min' => 650, 'med' => 1050, 'max' => 1500],
            ['code' => 'TPR-CHEM-21', 'cat' => 'Bahan Kimia Khusus Solven', 'method' => 'CPM', 'min' => 750, 'med' => 1200, 'max' => 1700],
            ['code' => 'TPR-TEXT-22', 'cat' => 'Benang Poliester Tekstil', 'method' => 'CUP', 'min' => 300, 'med' => 500, 'max' => 750],
            ['code' => 'TPR-MARK-23', 'cat' => 'Jasa Pemasaran Digital & Media', 'method' => 'TNMM', 'min' => 800, 'med' => 1300, 'max' => 1800],
            ['code' => 'TPR-ENG-24', 'cat' => 'Jasa Rekayasa Teknik & Desain', 'method' => 'TNMM', 'min' => 900, 'med' => 1400, 'max' => 2000],
            ['code' => 'TPR-PROP-25', 'cat' => 'Sewa Bangunan Kantor & Fasilitas', 'method' => 'CUP', 'min' => 500, 'med' => 800, 'max' => 1200],
        ];

        $ruleModels = [];
        foreach ($tpCategories as $idx => $cat) {
            $rule = TransferPricingRule::create([
                'rule_code' => $cat['code'],
                'product_category' => $cat['cat'],
                'tp_method' => $cat['method'],
                'min_arms_length_margin_percent' => $cat['min'] / 100,
                'max_arms_length_margin_percent' => $cat['max'] / 100,
                'min_margin_bps' => $cat['min'],
                'median_margin_bps' => $cat['med'],
                'max_margin_bps' => $cat['max'],
                'benchmark_industry_source' => 'OECD_STAT_ORBIS_2026_Q3',
                'is_active' => true,
            ]);
            $ruleModels[] = $rule;

            // Buat sampel 3 benchmark independen per rule
            TransferPricingBenchmark::create([
                'rule_id' => $rule->id,
                'comparator_name' => "PT Pembanding Independen {$idx}A",
                'operating_margin_bps' => $cat['min'] + 50,
                'gross_margin_bps' => $cat['min'] + 100,
                'financial_year' => '2026',
                'source_dataset' => 'ORBIS_BUREAU_VAN_DIJK',
            ]);
            TransferPricingBenchmark::create([
                'rule_id' => $rule->id,
                'comparator_name' => "PT Pembanding Independen {$idx}B",
                'operating_margin_bps' => $cat['med'],
                'gross_margin_bps' => $cat['med'] + 80,
                'financial_year' => '2026',
                'source_dataset' => 'ORBIS_BUREAU_VAN_DIJK',
            ]);
            TransferPricingBenchmark::create([
                'rule_id' => $rule->id,
                'comparator_name' => "PT Pembanding Independen {$idx}C",
                'operating_margin_bps' => $cat['max'] - 50,
                'gross_margin_bps' => $cat['max'],
                'financial_year' => '2026',
                'source_dataset' => 'ORBIS_BUREAU_VAN_DIJK',
            ]);
        }

        // 4. Registrasi >= 120 Transaksi Cermin Bilateral (ic_transactions)
        $statuses = [
            'draft', 'submitted', 'arm_length_validated',
            'posted_seller', 'posted_buyer', 'reconciled',
            'eliminated', 'reversed',
        ];

        $entityCodes = ['HOLDING', 'SUB_A_MANUF', 'SUB_B_DIST', 'SUB_C_TECH', 'SUB_D_LOGIST', 'SUB_E_OVERSEAS_SG'];
        $txRows = [];

        for ($i = 1; $i <= 125; $i++) {
            $sIdx = ($i % 6);
            $bIdx = (($i + 1) % 6);
            $seller = $entityCodes[$sIdx];
            $buyer = $entityCodes[$bIdx];

            $rule = $ruleModels[$i % count($ruleModels)];
            $status = $statuses[$i % count($statuses)];
            $amountIdr = (10_000_000 + ($i * 1_250_000));
            $txCode = sprintf('IC-T1-%04d', $i);

            $tx = IntercompanyTransaction::create([
                'transaction_code' => $txCode,
                'selling_entity' => $seller,
                'buying_entity' => $buyer,
                'selling_entity_id' => $entityModels[$seller]->id,
                'buying_entity_id' => $entityModels[$buyer]->id,
                'description' => "Pengiriman Batch Intercompany #{$i} ({$rule->product_category})",
                'currency' => ($seller === 'SUB_E_OVERSEAS_SG' || $buyer === 'SUB_E_OVERSEAS_SG') ? 'SGD' : 'IDR',
                'amount_idr' => $amountIdr,
                'amount_minor' => $amountIdr,
                'amount_functional_idr' => $amountIdr,
                'sales_invoice_ref' => "INV-{$txCode}",
                'purchase_bill_ref' => "BILL-{$txCode}",
                'tp_rule_id' => $rule->id,
                'status' => $status,
                'reconciled_at' => in_array($status, ['reconciled', 'eliminated']) ? $base->addDays($i % 25) : null,
                'eliminated_at' => ($status === 'eliminated') ? $base->addDays($i % 25) : null,
                'created_by' => 1,
                'approved_by' => in_array($status, ['arm_length_validated', 'posted_seller', 'posted_buyer', 'reconciled', 'eliminated']) ? 2 : null,
            ]);

            // Riwayat State Machine
            IntercompanyTransactionHistory::create([
                'transaction_id' => $tx->id,
                'from_status' => 'draft',
                'to_status' => $status,
                'reason' => 'Seeding T1 inisialisasi lifecycle transaksi',
                'operator_id' => 1,
            ]);
        }

        // 5. Registrasi >= 15 Fasilitas Pinjaman Intercompany (ic_loans) & Jadwal D6
        $loanPairs = [
            ['lender' => 'HOLDING', 'borrower' => 'SUB_A_MANUF', 'principal' => 500_000_000, 'base' => 600, 'spread' => 50, 'wht' => 1500],
            ['lender' => 'HOLDING', 'borrower' => 'SUB_B_DIST', 'principal' => 300_000_000, 'base' => 600, 'spread' => 75, 'wht' => 1500],
            ['lender' => 'HOLDING', 'borrower' => 'SUB_C_TECH', 'principal' => 200_000_000, 'base' => 600, 'spread' => 100, 'wht' => 1500],
            ['lender' => 'HOLDING', 'borrower' => 'SUB_D_LOGIST', 'principal' => 150_000_000, 'base' => 600, 'spread' => 125, 'wht' => 1500],
            ['lender' => 'HOLDING', 'borrower' => 'SUB_E_OVERSEAS_SG', 'principal' => 400_000_000, 'base' => 450, 'spread' => 80, 'wht' => 2000],
            ['lender' => 'SUB_A_MANUF', 'borrower' => 'SUB_B_DIST', 'principal' => 100_000_000, 'base' => 600, 'spread' => 50, 'wht' => 1500],
            ['lender' => 'SUB_A_MANUF', 'borrower' => 'SUB_C_TECH', 'principal' => 80_000_000, 'base' => 600, 'spread' => 60, 'wht' => 1500],
            ['lender' => 'SUB_C_TECH', 'borrower' => 'SUB_D_LOGIST', 'principal' => 60_000_000, 'base' => 600, 'spread' => 70, 'wht' => 1500],
            ['lender' => 'HOLDING', 'borrower' => 'SUB_A_MANUF', 'principal' => 250_000_000, 'base' => 600, 'spread' => 50, 'wht' => 1500],
            ['lender' => 'HOLDING', 'borrower' => 'SUB_B_DIST', 'principal' => 180_000_000, 'base' => 600, 'spread' => 90, 'wht' => 1500],
            ['lender' => 'HOLDING', 'borrower' => 'SUB_C_TECH', 'principal' => 120_000_000, 'base' => 600, 'spread' => 100, 'wht' => 1500],
            ['lender' => 'SUB_B_DIST', 'borrower' => 'SUB_D_LOGIST', 'principal' => 90_000_000, 'base' => 600, 'spread' => 80, 'wht' => 1500],
            ['lender' => 'SUB_A_MANUF', 'borrower' => 'SUB_D_LOGIST', 'principal' => 110_000_000, 'base' => 600, 'spread' => 50, 'wht' => 1500],
            ['lender' => 'HOLDING', 'borrower' => 'SUB_D_LOGIST', 'principal' => 70_000_000, 'base' => 600, 'spread' => 110, 'wht' => 1500],
            ['lender' => 'SUB_E_OVERSEAS_SG', 'borrower' => 'SUB_A_MANUF', 'principal' => 350_000_000, 'base' => 450, 'spread' => 90, 'wht' => 2000],
            ['lender' => 'HOLDING', 'borrower' => 'SUB_B_DIST', 'principal' => 140_000_000, 'base' => 600, 'spread' => 65, 'wht' => 1500],
        ];

        foreach ($loanPairs as $idx => $lp) {
            $loanNum = sprintf('IC-LN-2026-%03d', $idx + 1);
            $loan = IntercompanyLoan::create([
                'loan_agreement_number' => $loanNum,
                'lender_entity' => $lp['lender'],
                'borrower_entity' => $lp['borrower'],
                'lender_entity_id' => $entityModels[$lp['lender']]->id,
                'borrower_entity_id' => $entityModels[$lp['borrower']]->id,
                'principal_idr' => $lp['principal'],
                'principal_minor' => $lp['principal'],
                'currency' => 'IDR',
                'interest_rate_bps' => $lp['base'] + $lp['spread'],
                'spread_bps' => $lp['spread'],
                'withholding_tax_bps' => $lp['wht'],
                'arms_length_interest_rate' => ($lp['base'] + $lp['spread']) / 100,
                'start_date' => $base->toDateString(),
                'due_date' => $base->addMonths(12)->toDateString(),
                'repaid_principal_idr' => 0,
                'repaid_principal_minor' => 0,
                'status' => 'active',
            ]);

            // Jadwal 12 periode
            $principalPart = (int) intdiv($lp['principal'], 12);
            $rem = $lp['principal'] % 12;

            $currBal = $lp['principal'];
            for ($p = 1; $p <= 12; $p++) {
                $pComp = $principalPart + ($p === 12 ? $rem : 0);
                $grossInt = (int) intdiv($currBal * ($lp['base'] + $lp['spread']), 120000);
                $wht = (int) intdiv($grossInt * $lp['wht'], 10000);
                $netInt = $grossInt - $wht;
                $closeBal = $currBal - $pComp;

                IntercompanyLoanSchedule::create([
                    'loan_id' => $loan->id,
                    'installment_number' => $p,
                    'due_date' => $base->addMonths($p)->toDateString(),
                    'beginning_balance_minor' => $currBal,
                    'principal_due_minor' => $pComp,
                    'interest_due_minor' => $grossInt,
                    'tax_withheld_minor' => $wht,
                    'net_interest_minor' => $netInt,
                    'ending_balance_minor' => $closeBal,
                    'payment_status' => ($p <= 2) ? 'paid' : 'pending',
                    'paid_at' => ($p <= 2) ? $base->addMonths($p)->toDateTimeString() : null,
                ]);

                $currBal = $closeBal;
            }
        }

        // 6. Registrasi >= 50 Jurnal Eliminasi Konsolidasi (ic_elimination_entries)
        $elimTypes = ['reciprocal_ar_ap', 'sales_cogs', 'unrealized_profit', 'dividend', 'equity'];
        for ($j = 1; $j <= 52; $j++) {
            $eType = $elimTypes[$j % count($elimTypes)];
            $nominal = 15_000_000 + ($j * 1_500_000);
            $sEnt = $entityCodes[$j % 5];
            $bEnt = $entityCodes[($j + 1) % 5];

            EliminationEntry::create([
                'elimination_code' => sprintf('IC-ELIM-202610-%03d', $j),
                'period' => '2026-10',
                'elimination_type' => $eType,
                'selling_entity' => $sEnt,
                'buying_entity' => $bEnt,
                'selling_entity_id' => $entityModels[$sEnt]->id,
                'buying_entity_id' => $entityModels[$bEnt]->id,
                'debit_account' => "ic:ap:{$sEnt}:{$bEnt}:IDR",
                'credit_account' => "ic:ar:{$bEnt}:{$sEnt}:IDR",
                'amount_idr' => $nominal,
                'status' => 'posted',
            ]);
        }

        // 7. Registrasi Catatan NCI & Dividen Kepentingan Non-Pengendali
        $nciEntities = [
            ['sub' => 'SUB_B_DIST', 'parent_pct' => 80.0, 'nci_pct' => 20.0, 'income' => 120_000_000, 'equity' => 500_000_000],
            ['sub' => 'SUB_C_TECH', 'parent_pct' => 90.0, 'nci_pct' => 10.0, 'income' => 200_000_000, 'equity' => 800_000_000],
            ['sub' => 'SUB_D_LOGIST', 'parent_pct' => 70.0, 'nci_pct' => 30.0, 'income' => 90_000_000, 'equity' => 350_000_000],
        ];

        foreach ($nciEntities as $n) {
            $nciModel = SubsidiaryNci::create([
                'subsidiary_name' => $n['sub'],
                'subsidiary_entity_id' => $entityModels[$n['sub']]->id,
                'parent_ownership_percent' => $n['parent_pct'],
                'nci_ownership_percent' => $n['nci_pct'],
                'parent_ownership_permille' => (int) ($n['parent_pct'] * 1000),
                'nci_ownership_permille' => (int) ($n['nci_pct'] * 1000),
                'net_income_idr' => $n['income'],
                'nci_share_net_income_idr' => (int) ($n['income'] * $n['nci_pct'] / 100),
                'period' => '2026-10',
            ]);

            NciDividend::create([
                'subsidiary_entity_id' => $entityModels[$n['sub']]->id,
                'declaration_date' => $base->addDays(15)->toDateString(),
                'total_dividend_idr' => 40_000_000,
                'nci_dividend_idr' => (int) (40_000_000 * $n['nci_pct'] / 100),
                'status' => 'paid',
            ]);
        }

        // 8. Registrasi Perjanjian Cost Sharing (CSA) & Transfer Aset
        $csa = CostSharingAgreement::create([
            'agreement_code' => 'CSA-IT-2026',
            'agreement_name' => 'Perjanjian Bersama Layanan TI & Cloud ERP Nusantara',
            'provider_entity_id' => $entityModels['HOLDING']->id,
            'allocation_basis' => 'REVENUE',
            'service_category' => 'SHARED_SERVICES',
            'markup_bps' => 500,
            'is_active' => true,
        ]);

        CostSharingAllocation::create([
            'agreement_id' => $csa->id,
            'period' => '2026-10',
            'beneficiary_entity_id' => $entityModels['SUB_A_MANUF']->id,
            'allocation_ratio_permille' => 50000,
            'allocated_cost_idr' => 90_000_000,
            'markup_amount_idr' => 4_500_000,
            'total_billed_idr' => 94_500_000,
        ]);

        CostSharingAllocation::create([
            'agreement_id' => $csa->id,
            'period' => '2026-10',
            'beneficiary_entity_id' => $entityModels['SUB_B_DIST']->id,
            'allocation_ratio_permille' => 50000,
            'allocated_cost_idr' => 90_000_000,
            'markup_amount_idr' => 4_500_000,
            'total_billed_idr' => 94_500_000,
        ]);
    }

    /**
     * Skenario Kejutan L.4: "Lonjakan diskrepansi bilateral barang dalam perjalanan & penolakan margin wajar"
     */
    public function injectShock(): void
    {
        // 1. Injeksi diskrepansi transaksi bilateral entitas sama
        IntercompanyTransaction::query()->latest('id')->first()?->update([
            'selling_entity' => 'HOLDING',
            'buying_entity' => 'HOLDING',
            'description' => '[SHOCK: Transaksi sirkular tidak wajar]',
        ]);

        // 2. Injeksi diskrepansi jadwal pinjaman (jadwal pokok tidak sama dengan pokok)
        IntercompanyLoanSchedule::query()->latest('id')->first()?->update([
            'principal_due_minor' => 100, // Merusak sum principal
        ]);

        // 3. Injeksi eliminasi akun identik
        EliminationEntry::query()->latest('id')->first()?->update([
            'credit_account' => 'ic:ap:HOLDING:SUB_A_MANUF:IDR',
            'debit_account' => 'ic:ap:HOLDING:SUB_A_MANUF:IDR',
        ]);
    }
}
