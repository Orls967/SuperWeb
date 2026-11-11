<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\database\seeders;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\EnterpriseFinance\Domain\Models\BudgetHistory;
use Modules\EnterpriseFinance\Domain\Models\ComplianceDeadline;
use Modules\EnterpriseFinance\Domain\Models\ConsolidationEntry;
use Modules\EnterpriseFinance\Domain\Models\ConsolidationRun;
use Modules\EnterpriseFinance\Domain\Models\DebtCovenantEvaluation;
use Modules\EnterpriseFinance\Domain\Models\EfSegmentFinancialReport;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseTaxSummary;
use Modules\EnterpriseFinance\Domain\Models\SodRule;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;
use Modules\EnterpriseFinance\Domain\Models\WorkingCapitalMetric;

/**
 * Seeder T1 Modul Keuangan Grup, Konsolidasi & Tata Kelola Korporat (W8-P5 / Fase 54).
 *
 * Dataset deterministik skala enterprise:
 * - >= 50 Anggaran Departemen (ef_budgets) mencakup 8 status siklus hidup anggaran
 * - >= 10 Fasilitas Kredit Sindikasi (ef_syndicated_facilities) & Evaluasi Covenant D7
 * - >= 10 Putaran Konsolidasi Trial Balance (ef_consolidation_runs) & Rincian Akun D8
 * - >= 20 Ringkasan Masa Pajak PPN & PPh (ef_tax_summaries)
 * - >= 15 Aturan Pemisahan Tugas (ef_sod_rules)
 * - >= 20 Agenda Kalender Regulasi OJK, DJP, BEI (ef_compliance_deadlines)
 * - >= 15 Metrik Optimalisasi Modal Kerja CCC (ef_working_capital_metrics)
 * - 17 Baris Segment Financial Reporting (ef_segment_financial_reports)
 */
class EnterpriseFinanceT1Seeder extends Seeder
{
    public const BASE_DATE = '2026-10-01';

    public function run(): void
    {
        $this->call(LedgerAccountsSeeder::class);

        $base = CarbonImmutable::parse(self::BASE_DATE);

        // 1. Bersihkan tabel EnterpriseFinance secara idempoten
        DB::table('ef_working_capital_metrics')->delete();
        DB::table('ef_consolidation_entries')->delete();
        DB::table('ef_consolidation_runs')->delete();
        DB::table('ef_debt_covenant_evaluations')->delete();
        DB::table('ef_syndicated_facilities')->delete();
        DB::table('ef_budget_histories')->delete();
        DB::table('ef_budgets')->delete();
        DB::table('ef_tax_summaries')->delete();
        DB::table('ef_sod_rules')->delete();
        DB::table('ef_compliance_deadlines')->delete();
        DB::table('ef_capex_proposals')->delete();
        DB::table('ef_ma_acquisitions')->delete();
        DB::table('ef_segment_financial_reports')->delete();

        // 2. Registrasi >= 50 Anggaran Departemen (ef_budgets)
        $costCenters = [
            'IT_INFRA', 'SOFTWARE_ENG', 'DATA_AI', 'CYBER_SEC',
            'FLEET_OPS', 'WAREHOUSING', 'CUSTOMS_CLEARING', 'LAST_MILE',
            'FINANCE_TREASURY', 'LEGAL_COMPLIANCE', 'HR_TALENT', 'PROCUREMENT',
            'MARKETING_GROWTH', 'SALES_ENTERPRISE', 'CUSTOMER_SUPPORT', 'AUDIT_INTERNAL',
        ];

        $statuses = ['DRAFT', 'SUBMITTED', 'REVIEWED', 'APPROVED', 'ACTIVE', 'REVISED', 'FROZEN', 'CLOSED'];

        for ($i = 1; $i <= 55; $i++) {
            $cc = $costCenters[$i % count($costCenters)];
            $st = $statuses[$i % count($statuses)];
            $year = ($i <= 30) ? '2026' : '2025';
            $allocated = 200_000_000 + ($i * 50_000_000);
            $spent = (int) ($allocated * (($i % 5) * 15) / 100);
            $encumbered = (int) ($allocated * 10 / 100);

            $budget = EnterpriseBudget::create([
                'budget_code' => sprintf('BGT-%s-%s-%02d', $year, $cc, $i),
                'fiscal_year' => $year,
                'cost_center_code' => $cc,
                'account_code' => sprintf('5%03d-EXP', $i),
                'allocated_amount_idr' => $allocated,
                'encumbered_amount_idr' => $encumbered,
                'spent_amount_idr' => $spent,
                'control_type' => ($i % 4 === 0) ? 'ADVISORY' : 'HARD_STOP',
                'status' => $st,
            ]);

            BudgetHistory::create([
                'budget_id' => $budget->id,
                'from_status' => 'DRAFT',
                'to_status' => $st,
                'reason' => 'Seeding baseline anggaran operasional korporat T1',
            ]);
        }

        // 3. Registrasi >= 10 Fasilitas Kredit Sindikasi & Evaluasi Covenant D7
        $arrangers = ['Bank Mandiri Konsorsium', 'BCA Syndication Desk', 'BNI Structured Finance', 'DBS Global Finance'];
        $facilities = [];

        for ($k = 1; $k <= 12; $k++) {
            $commitment = 500_000_000_000 + ($k * 100_000_000_000);
            $drawn = (int) ($commitment * 70 / 100);
            $repaid = (int) ($drawn * 20 / 100);

            $fac = SyndicatedFacility::create([
                'facility_code' => sprintf('FAC-SYND-%03d', $k),
                'facility_name' => "Fasilitas Sindikasi Pembiayaan Infrastruktur #{$k}",
                'facility_type' => ($k % 2 === 0) ? 'TERM_LOAN' : 'REVOLVING',
                'lead_arranger' => $arrangers[$k % count($arrangers)],
                'currency' => 'IDR',
                'total_commitment_minor' => $commitment,
                'drawn_amount_minor' => $drawn,
                'repaid_amount_minor' => $repaid,
                'interest_rate_bps' => 625 + ($k * 10),
                'spread_bps' => 200 + ($k * 5),
                'start_date' => $base->subMonths(12)->toDateString(),
                'maturity_date' => $base->addMonths(36)->toDateString(),
                'status' => 'ACTIVE',
            ]);

            $facilities[] = $fac;

            DebtCovenantEvaluation::create([
                'facility_id' => $fac->id,
                'evaluation_period' => '2026-Q3',
                'dscr_permille' => 1650 + ($k * 20),
                'der_permille' => 1800 - ($k * 25),
                'iscr_permille' => 3200 + ($k * 30),
                'current_ratio_permille' => 1450 + ($k * 15),
                'is_compliant' => true,
                'sha256_signature' => hash('sha256', "CERT:{$fac->facility_code}:2026-Q3"),
                'certificate_payload_json' => json_encode(['compliant' => true, 'facility' => $fac->facility_code]),
            ]);
        }

        // 4. Registrasi >= 10 Putaran Konsolidasi Trial Balance D8
        for ($m = 1; $m <= 12; $m++) {
            $periodStr = sprintf('2026-%02d', ($m % 12) + 1);
            $totDebit = 1_000_000_000_000 + ($m * 50_000_000_000);
            $totCredit = $totDebit; // Invarian: Zero-Sum Seimbang!

            $run = ConsolidationRun::create([
                'run_code' => sprintf('RUN-TB-%s-%03d', $periodStr, $m),
                'period' => $periodStr,
                'reporting_currency' => 'IDR',
                'total_entities_count' => 6,
                'total_debit_minor' => $totDebit,
                'total_credit_minor' => $totCredit,
                'elimination_total_minor' => 150_000_000_000,
                'is_balanced' => true,
                'sha256_signature' => hash('sha256', "RUN:{$periodStr}:{$totDebit}"),
                'status' => 'PUBLISHED',
            ]);

            ConsolidationEntry::create([
                'consolidation_run_id' => $run->id,
                'entity_code' => 'CONSOLIDATED',
                'canonical_account_code' => '1101-CASH',
                'account_name' => 'Kas dan Setara Kas Grup',
                'local_debit_minor' => $totDebit,
                'local_credit_minor' => 0,
                'elimination_debit_minor' => 0,
                'elimination_credit_minor' => 0,
                'consolidated_net_minor' => $totDebit,
            ]);

            ConsolidationEntry::create([
                'consolidation_run_id' => $run->id,
                'entity_code' => 'CONSOLIDATED',
                'canonical_account_code' => '3101-CAPITAL',
                'account_name' => 'Modal Saham Konsolidasian',
                'local_debit_minor' => 0,
                'local_credit_minor' => $totCredit,
                'elimination_debit_minor' => 0,
                'elimination_credit_minor' => 0,
                'consolidated_net_minor' => -$totCredit,
            ]);
        }

        // 5. Registrasi >= 20 Ringkasan Pajak Masa PPN & PPh
        $taxTypes = ['PPN', 'PPH_23'];
        for ($month = 1; $month <= 12; $month++) {
            $periodStr = sprintf('2026-%02d', $month);
            foreach ($taxTypes as $tType) {
                $dpp = 50_000_000_000 + ($month * 2_000_000_000);
                $inputVat = (int) ($dpp * 8 / 100);
                $outputVat = (int) ($dpp * 11 / 100);
                $payable = ($tType === 'PPN') ? ($outputVat - $inputVat) : (int) ($dpp * 2 / 100);

                EnterpriseTaxSummary::create([
                    'period' => $periodStr,
                    'tax_type' => $tType,
                    'tax_base_idr' => $dpp,
                    'input_tax_idr' => $inputVat,
                    'output_tax_idr' => $outputVat,
                    'withheld_tax_idr' => (int) ($dpp * 2 / 100),
                    'payable_or_refundable_idr' => $payable,
                    'status' => 'settled',
                ]);
            }
        }

        // 6. Registrasi >= 15 Aturan Segregation of Duties (SoD)
        $sodPairs = [
            ['maker', 'checker', 'Pemisahan pembuatan dan persetujuan pengeluaran kas', 'CRITICAL'],
            ['procurement_officer', 'payment_disburser', 'Pemisahan pesanan pembelian dan pencairan dana kas', 'CRITICAL'],
            ['treasury_operator', 'bank_reconciler', 'Pemisahan pengelola rekening kas dan rekonsiliasi bank', 'CRITICAL'],
            ['it_admin', 'ledger_poster', 'Pemisahan administrator sistem dan pemosting jurnal buku besar', 'CRITICAL'],
            ['tax_preparer', 'tax_approver', 'Pemisahan penyusun laporan pajak dan penandatangan SPT', 'HIGH'],
            ['sales_rep', 'credit_approver', 'Pemisahan staf penjualan dan otorisasi batas kredit pelanggan', 'HIGH'],
            ['warehouse_custodian', 'inventory_auditor', 'Pemisahan pengelola fisik stok gudang dan tim stock opname', 'CRITICAL'],
            ['hr_payroll', 'payroll_disburser', 'Pemisahan input data gaji pegawai dan eksekusi transfer payroll', 'CRITICAL'],
            ['asset_custodian', 'asset_disposer', 'Pemisahan pengurus aset tetap dan otorisasi pelepasan/penjualan aset', 'HIGH'],
            ['general_ledger_accountant', 'internal_auditor', 'Pemisahan akuntan buku besar dan auditor internal', 'CRITICAL'],
            ['debt_covenant_evaluator', 'facility_borrower', 'Pemisahan penilai rasio covenant dan pejabat penarik utang', 'HIGH'],
            ['budget_creator', 'budget_approver', 'Pemisahan pembuat usulan anggaran dan otorisator pagu', 'CRITICAL'],
            ['vendor_registrar', 'invoice_approver', 'Pemisahan pendaftar data master vendor dan persetujuan faktur', 'HIGH'],
            ['cashier', 'petty_cash_auditor', 'Pemisahan pemegang kas kecil dan pemeriksa bukti kas', 'CRITICAL'],
            ['contract_negotiator', 'legal_signatory', 'Pemisahan negosiator kontrak dan penandatangan hukum korporat', 'HIGH'],
            ['goodwill_assessor', 'cgu_manager', 'Pemisahan penilai penurunan nilai goodwill dan manajer unit bisnis', 'HIGH'],
        ];

        foreach ($sodPairs as $idx => $sp) {
            SodRule::create([
                'rule_code' => sprintf('SOD-RULE-%03d', $idx + 1),
                'role_a' => $sp[0],
                'role_b' => $sp[1],
                'description' => $sp[2],
                'risk_level' => $sp[3],
                'is_active' => true,
            ]);
        }

        // 7. Registrasi >= 20 Agenda Kalender Regulasi
        $regBodies = ['DJP', 'OJK', 'BEI', 'KEMENDAG', 'BKPM'];
        for ($r = 1; $r <= 22; $r++) {
            ComplianceDeadline::create([
                'item_code' => sprintf('REG-ITEM-%03d', $r),
                'title' => "Kewajiban Pelaporan Berkala Otoritas #{$r}",
                'regulatory_body' => $regBodies[$r % count($regBodies)],
                'due_date' => $base->addDays($r * 15)->toDateString(),
                'assigned_role' => 'auditor',
                'status' => ($r <= 5) ? 'completed' : 'pending',
            ]);
        }

        // 8. Registrasi >= 15 Metrik Optimalisasi Modal Kerja CCC
        for ($w = 1; $w <= 16; $w++) {
            $rev = 100_000_000_000 + ($w * 10_000_000_000);
            $cogs = (int) ($rev * 65 / 100);
            $ar = (int) ($rev * 40 / 365);
            $inv = (int) ($cogs * 25 / 365);
            $ap = (int) ($cogs * 50 / 365);

            WorkingCapitalMetric::create([
                'entity_code' => sprintf('ENT_UNIT_%02d', $w),
                'period' => '2026-10',
                'revenue_minor' => $rev,
                'cogs_minor' => $cogs,
                'ar_balance_minor' => $ar,
                'inventory_balance_minor' => $inv,
                'ap_balance_minor' => $ap,
                'dso_days' => 40,
                'dio_days' => 25,
                'dpo_days' => 50,
                'ccc_days' => 15,
                'efficiency_tier' => 'EXCELLENT',
            ]);
        }

        // 9. Registrasi 17 Baris Segment Financial Reporting (ef_segment_financial_reports)
        $segmentContributions = [];
        $totalConsolidatedRevenue = 0;
        $totalConsolidatedEbitda = 0;

        for ($s = 1; $s <= 17; $s++) {
            $sRev = 20_000_000_000 + ($s * 5_000_000_000);
            $sCogs = (int) ($sRev * 60 / 100);
            $sOpex = (int) ($sRev * 15 / 100);
            $sEbitda = $sRev - ($sCogs + $sOpex);

            $segmentContributions["LINE_{$s}_SEGMENT"] = [
                'revenue_minor' => $sRev,
                'ebitda_minor' => $sEbitda,
            ];

            $totalConsolidatedRevenue += $sRev;
            $totalConsolidatedEbitda += $sEbitda;
        }

        EfSegmentFinancialReport::create([
            'id' => (string) Str::uuid(),
            'report_code' => 'SEG-REP-2026-Q3',
            'period_quarter' => '2026-Q3',
            'segment_contributions' => $segmentContributions,
            'total_consolidated_revenue_minor' => $totalConsolidatedRevenue,
            'total_consolidated_ebitda_minor' => $totalConsolidatedEbitda,
        ]);
    }

    /**
     * Skenario Kejutan L.4: "Injeksi Diskrepansi Invarian Integritas Keuangan Korporat"
     */
    public function injectShock(): void
    {
        // 1. Merusak Invarian Anggaran Hard-Stop (Spent melebihi Alokasi Pagu)
        EnterpriseBudget::where('control_type', 'HARD_STOP')->latest('id')->first()?->update([
            'spent_amount_idr' => 999_999_999_999, // Overspend masif yang melanggar batas
        ]);

        // 2. Merusak Invarian Zero-Sum Konsolidasi Trial Balance (Total Debit != Total Credit)
        ConsolidationRun::latest('id')->first()?->update([
            'total_credit_minor' => 1, // Neraca tidak seimbang
        ]);

        // 3. Merusak Invarian Sindikasi (Penarikan melebihi Komitmen Maksimum)
        SyndicatedFacility::latest('id')->first()?->update([
            'drawn_amount_minor' => 999_999_999_999,
        ]);
    }
}
