<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Services;

use Modules\Intercompany\Domain\Algorithms\MultivariateBenchmarkComparator;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\TransferPricingRule;
use Modules\Intercompany\Domain\Services\TransferPricingLocalFileGenerator;

/**
 * Layanan Ekspor Paket Audit Kepatuhan Transfer Pricing Grup Konglomerasi (PMK 172/2023).
 *
 * Menggabungkan dokumentasi Local File, kertas kerja benchmark multivariat pembanding,
 * riwayat penyesuaian laba (true-up adjustments), serta rekonsiliasi SPT Tahunan PPh Badan.
 * Seluruh kalkulasi beroperasi pada bilangan bulat (integer minor units sen / basis points) tanpa float.
 */
class GroupTransferPricingAuditPackExporter
{
    public function __construct(
        protected readonly TransferPricingLocalFileGenerator $localFileGen,
        protected readonly MultivariateBenchmarkComparator $benchmarkComp
    ) {}

    /**
     * Susun paket data audit transfer pricing lengkap untuk otoritas pajak atau auditor eksternal.
     *
     * @param array{
     *     entity_code: string,
     *     fiscal_year: string,
     *     rule_code: string,
     *     tested_pli?: string
     * } $config
     * @param array{
     *     revenue_minor: int,
     *     cogs_minor: int,
     *     opex_minor: int,
     *     operating_profit_minor: int,
     *     accounts_receivable_minor: int,
     *     inventory_minor: int,
     *     accounts_payable_minor: int
     * } $financialMetrics
     * @param array<int, array{
     *     name: string,
     *     revenue_minor: int,
     *     cogs_minor: int,
     *     opex_minor: int,
     *     operating_profit_minor: int,
     *     accounts_receivable_minor: int,
     *     inventory_minor: int,
     *     accounts_payable_minor: int
     * }> $comparators
     * @return array{
     *     audit_pack_id: string,
     *     entity_code: string,
     *     legal_name: string,
     *     fiscal_year: string,
     *     tax_id_npwp: string,
     *     country_code: string,
     *     rule_summary: array<string, mixed>,
     *     benchmark_evaluation: array<string, mixed>,
     *     local_file_dossier: array<string, mixed>,
     *     tax_reconciliation_summary: array{
     *         commercial_profit_minor: int,
     *         fiscal_positive_adjustment_minor: int,
     *         fiscal_negative_adjustment_minor: int,
     *         taxable_income_minor: int,
     *         corporate_tax_liability_minor: int
     *     },
     *     generated_at: string,
     *     audit_dossier_hash: string
     * }
     */
    public function exportAuditPack(
        array $config,
        array $financialMetrics,
        array $comparators
    ): array {
        $entityCode = strtoupper($config['entity_code']);
        $year = $config['fiscal_year'];
        $ruleCode = $config['rule_code'];

        $entity = IntercompanyEntity::where('entity_code', $entityCode)->first();
        $legalName = $entity?->legal_name ?? "PT {$entityCode} Indonesia";
        $npwp = $entity?->tax_id_npwp ?? '01.000.000.0-000.000';
        $country = $entity?->country_code ?? 'ID';

        $rule = TransferPricingRule::where('rule_code', $ruleCode)->first();
        $ruleSummary = [
            'rule_code' => $ruleCode,
            'category' => $rule?->product_category ?? 'MANUFACTURING_ASSEMBLY',
            'tp_method' => $rule?->tp_method ?? 'TNMM',
            'min_margin_bps' => $rule?->min_margin_bps ?? 600,
            'median_margin_bps' => $rule?->median_margin_bps ?? 1000,
            'max_margin_bps' => $rule?->max_margin_bps ?? 1400,
        ];

        // 1. Eksekusi Komparasi Multivariat
        $benchConfig = [
            'rule_code' => $ruleCode,
            'tested_entity_code' => $entityCode,
            'method' => $rule?->tp_method ?? 'TNMM',
            'period' => $year,
        ];

        $benchResult = $this->benchmarkComp->evaluate($benchConfig, $financialMetrics, $comparators);
        $benchArray = $benchResult->toArray();

        // 2. Susun Local File Dossier
        $profile = [
            'entity_code' => $entityCode,
            'fiscal_year' => $year,
            'tax_id_npwp' => $npwp,
            'business_sector' => 'Automotive & Logistics Assembly',
            'parent_holding_code' => 'HOLDING_CORP',
        ];

        $txList = [
            [
                'transaction_code' => "TX-TP-{$entityCode}-{$year}-01",
                'counterparty_code' => 'HOLDING_CORP',
                'transaction_type' => 'TANGIBLE_GOODS',
                'annual_volume_minor' => $financialMetrics['revenue_minor'],
                'selected_tp_method' => 'TNMM',
                'tested_pli' => 'OPERATING_MARGIN',
                'actual_tested_margin_bps' => $benchArray['tested_party_evaluation']['actual_margin_bps'],
            ],
        ];

        $far = [
            'functions_score' => ['r_and_d' => 10, 'manufacturing' => 85, 'distribution' => 70, 'quality_control' => 80],
            'assets_score' => ['plant' => 90, 'equipment' => 85, 'working_capital' => 75],
            'risks_score' => ['inventory' => 40, 'credit' => 30, 'market' => 45],
        ];

        $economic = [
            'q1_margin_bps' => $benchArray['interquartile_range']['q1_bps'],
            'median_margin_bps' => $benchArray['interquartile_range']['median_bps'],
            'q3_margin_bps' => $benchArray['interquartile_range']['q3_bps'],
            'comparators_count' => count($comparators),
            'source_database' => 'OECD_AMNE_2026_ID',
        ];

        $localFile = $this->localFileGen->generateLocalFile($profile, $txList, $far, $economic);

        // 3. Rekonsiliasi Fiskal SPT Badan (Corporate Income Tax Reconciliation)
        $commercialProfit = $financialMetrics['operating_profit_minor'];
        $trueUpMinor = $benchArray['tested_party_evaluation']['recommended_adjustment_minor'];
        $adjDir = $benchArray['tested_party_evaluation']['adjustment_direction'];

        $posAdjustment = ($adjDir === 'UPWARD') ? $trueUpMinor : 0;
        $negAdjustment = ($adjDir === 'DOWNWARD') ? $trueUpMinor : 0;
        $taxableIncome = max(0, $commercialProfit + $posAdjustment - $negAdjustment);
        $taxLiability = intdiv($taxableIncome * 2200, 10000); // 22% tarif PPh Badan

        $taxReconciliation = [
            'commercial_profit_minor' => $commercialProfit,
            'fiscal_positive_adjustment_minor' => $posAdjustment,
            'fiscal_negative_adjustment_minor' => $negAdjustment,
            'taxable_income_minor' => $taxableIncome,
            'corporate_tax_liability_minor' => $taxLiability,
        ];

        $packId = sprintf('AUD-PACK-%s-%s', $entityCode, $year);
        $nowStr = gmdate('Y-m-d\TH:i:s\Z');
        $hashSeed = sprintf(
            '%s|%s|%s|%d|%d|%d',
            $packId,
            $entityCode,
            $year,
            $commercialProfit,
            $taxableIncome,
            $taxLiability
        );
        $dossierHash = hash('sha256', $hashSeed);

        return [
            'audit_pack_id' => $packId,
            'entity_code' => $entityCode,
            'legal_name' => $legalName,
            'fiscal_year' => $year,
            'tax_id_npwp' => $npwp,
            'country_code' => $country,
            'rule_summary' => $ruleSummary,
            'benchmark_evaluation' => $benchArray,
            'local_file_dossier' => $localFile,
            'tax_reconciliation_summary' => $taxReconciliation,
            'generated_at' => $nowStr,
            'audit_dossier_hash' => $dossierHash,
        ];
    }
}
