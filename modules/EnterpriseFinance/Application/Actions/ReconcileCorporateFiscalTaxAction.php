<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Actions;

use Modules\EnterpriseFinance\Application\Services\CorporateFiscalReconciliationEngine;
use Modules\EnterpriseFinance\Application\Services\TaxFixtureDataSimulator;
use Modules\EnterpriseFinance\Domain\DTOs\FiscalReconciliationResultDTO;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseTaxSummary;

/**
 * Aksi Rekonsiliasi Fiskal Korporat & Sinkronisasi Fixture Pajak (54.3).
 * Melakukan penyesuaian laba komersial ke fiskal, menghitung PPh Badan terutang,
 * dan menghasilkan fixture data dokumen e-Faktur / e-Bupot untuk uji kepatuhan.
 */
class ReconcileCorporateFiscalTaxAction
{
    public function __construct(
        protected readonly CorporateFiscalReconciliationEngine $reconciliationEngine,
        protected readonly TaxFixtureDataSimulator $fixtureSimulator
    ) {}

    public function execute(
        string $fiscalYear,
        string $entityCode,
        int $commercialNetProfitIdr,
        array $positiveCorrections,
        array $negativeCorrections,
        int $taxCreditsIdr = 0
    ): FiscalReconciliationResultDTO {
        $result = $this->reconciliationEngine->reconcile(
            fiscalYear: $fiscalYear,
            entityCode: $entityCode,
            commercialNetProfitIdr: $commercialNetProfitIdr,
            positiveCorrections: $positiveCorrections,
            negativeCorrections: $negativeCorrections,
            taxCreditsPph22Pph23Pph25Idr: $taxCreditsIdr
        );

        // Rekam ringkasan pajak ke tabel database
        EnterpriseTaxSummary::updateOrCreate(
            [
                'period' => $fiscalYear,
                'tax_type' => 'PPH_BADAN',
            ],
            [
                'tax_base_idr' => $result['fiscal_taxable_income_idr'],
                'input_tax_idr' => $result['tax_credits_idr'],
                'output_tax_idr' => $result['corporate_tax_due_idr'],
                'withheld_tax_idr' => 0,
                'payable_or_refundable_idr' => $result['underpayment_pph29_idr'],
                'status' => ($result['underpayment_pph29_idr'] === 0) ? 'settled' : 'draft',
            ]
        );

        return new FiscalReconciliationResultDTO(
            fiscalYear: $result['fiscal_year'],
            entityCode: $result['entity_code'],
            commercialNetProfitMinor: $result['commercial_net_profit_idr'],
            totalPositiveCorrectionMinor: $result['total_positive_correction_idr'],
            totalNegativeCorrectionMinor: $result['total_negative_correction_idr'],
            fiscalTaxableIncomeMinor: $result['fiscal_taxable_income_idr'],
            corporateTaxDueMinor: $result['corporate_tax_due_idr'],
            effectiveTaxRateBps: $result['effective_tax_rate_bps'],
            taxCreditsMinor: $result['tax_credits_idr'],
            underpaymentPph29Minor: $result['underpayment_pph29_idr'],
            reconciledAt: date('c')
        );
    }
}
