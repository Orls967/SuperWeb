<?php

declare(strict_types=1);

namespace Modules\Hospital\Ledger;

use Modules\Banking\Domain\Enums\AccountKind;

/**
 * Bagan Akun Standar (Chart of Accounts) untuk modul Hospital.
 * Didaftarkan ke LedgerAccountsSeeder (KONSEP.md §A2.3).
 */
final class HospitalAccounts
{
    /**
     * @return list<array{code: string, name: string, kind: AccountKind, asset_code: string, allow_negative: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'hsp:cash:IDR',
                'name' => 'Hospital Outpatient Cash On Hand',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:doctor_fee_revenue:IDR',
                'name' => 'Hospital Outpatient Doctor Fee Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'hsp:pharmacy_revenue:IDR',
                'name' => 'Hospital Outpatient Pharmacy Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'hsp:cogs_pharmacy:IDR',
                'name' => 'Hospital Outpatient Pharmacy Cost of Goods Sold',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:inventory_pharmacy:IDR',
                'name' => 'Hospital Pharmacy Inventory',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:patient_receivable:IDR',
                'name' => 'Hospital Patient Outpatient Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:bpjs_claim_variance:IDR',
                'name' => 'Hospital BPJS INA-CBG Claim Rate Variance',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:bpjs_rejected_claim:IDR',
                'name' => 'Hospital BPJS Rejected Claim Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:bpjs_receivable:IDR',
                'name' => 'Hospital BPJS Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:epharmacy_receivable:IDR',
                'name' => 'Hospital e-Pharmacy Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:epharmacy_revenue:IDR',
                'name' => 'Hospital e-Pharmacy Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'hsp:hospital_revenue:IDR',
                'name' => 'Hospital Inpatient & Outpatient Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'hsp:imaging_receivable:IDR',
                'name' => 'Hospital Imaging Center Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:imaging_revenue:IDR',
                'name' => 'Hospital Imaging Center Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'hsp:insurance_receivable:IDR',
                'name' => 'Hospital Private Insurance Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:lab_receivable:IDR',
                'name' => 'Hospital Central Lab Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:lab_revenue:IDR',
                'name' => 'Hospital Central Lab Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'hsp:patient_deposit_escrow:IDR',
                'name' => 'Hospital Patient Deposit Escrow',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:patient_self_pay:IDR',
                'name' => 'Hospital Patient Self-Pay Cash Clearing',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:pharma_supplier_payable:IDR',
                'name' => 'Hospital Pharmaceutical Supplier Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:tourism_escrow_deposit:IDR',
                'name' => 'Medical Tourism Patient Escrow Deposit',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:tourism_unearned_escrow:IDR',
                'name' => 'Medical Tourism Unearned Package Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:trial_research_revenue:IDR',
                'name' => 'Clinical Trial Grant Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ],
            [
                'code' => 'hsp:trial_sponsor_receivable:IDR',
                'name' => 'Clinical Trial Sponsor Receivable',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:unearned_deposit:IDR',
                'name' => 'Hospital Patient Unearned Deposit Liability',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:inter_branch_clearing:IDR',
                'name' => 'Hospital Pharmacy Inter-Branch Clearing Account',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'hsp:vaccine_dispute_escrow:IDR',
                'name' => 'Vaccine Cold-Chain Dispute Escrow',
                'kind' => AccountKind::ESCROW,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'htl:hotel_vendor_payable:IDR',
                'name' => 'Medical Tourism Partner Hotel Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
            [
                'code' => 'lgx:transport_vendor_payable:IDR',
                'name' => 'Medical Tourism Shuttle Transport Payable',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ],
        ];
    }
}
