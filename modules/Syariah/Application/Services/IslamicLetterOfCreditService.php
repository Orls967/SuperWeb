<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Syariah\Domain\Enums\IslamicTradeContractType;
use Modules\Syariah\Domain\Enums\IslamicTradeStatus;
use Modules\Syariah\Domain\Models\SybIstisnaContract;
use Modules\Syariah\Domain\Models\SybIstisnaMilestone;
use Modules\Syariah\Domain\Models\SybLetterOfCredit;
use Modules\Syariah\Domain\Models\SybTradeAuditLog;
use Modules\Syariah\Domain\Models\SybTradeSettlement;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

final class IslamicLetterOfCreditService
{
    public function __construct(
        private readonly ShariahLedgerPoster $ledgerPoster,
    ) {}

    public function issueLetterOfCredit(
        string $applicantPartyRef,
        string $beneficiaryPartyRef,
        string $issuingBankPartyRef,
        string $amountCurrency,
        int $amountCents,
        int $marginDepositCents,
        CarbonImmutable $expiryDate,
        int $wakalahFeeCents = 15_000_000,
        ?string $termsConditions = null
    ): SybLetterOfCredit {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Nominal LC harus bernilai positif.');
        }
        if ($marginDepositCents < 0 || $marginDepositCents > $amountCents) {
            throw new InvalidArgumentException('Setoran margin deposit tidak valid.');
        }

        SyariahAccounts::ensureAccountsExist();

        return DB::transaction(function () use (
            $applicantPartyRef,
            $beneficiaryPartyRef,
            $issuingBankPartyRef,
            $amountCurrency,
            $amountCents,
            $marginDepositCents,
            $expiryDate,
            $wakalahFeeCents,
            $termsConditions
        ) {
            $count = SybLetterOfCredit::count() + 1;
            $lcNumber = sprintf('LC-SYB-%s-%04d', $expiryDate->format('Ym'), $count);

            $lc = SybLetterOfCredit::create([
                'lc_number' => $lcNumber,
                'applicant_party_ref' => $applicantPartyRef,
                'beneficiary_party_ref' => $beneficiaryPartyRef,
                'issuing_bank_party_ref' => $issuingBankPartyRef,
                'amount_currency' => $amountCurrency,
                'amount_cents' => $amountCents,
                'margin_deposit_cents' => $marginDepositCents,
                'status' => IslamicTradeStatus::ACTIVE->value,
                'expiry_date' => $expiryDate->toDateString(),
                'wakalah_fee_cents' => $wakalahFeeCents,
                'terms_conditions' => $termsConditions ?? 'Penerbitan LC Syariah berakad Wakalah bil Ujrah dan Istisna manufaktur.',
            ]);

            // Posting ledger:
            // Jaminan margin deposit: Debit cash pool, Kredit syb:trade_lc_margin_deposit:IDR
            if ($marginDepositCents > 0) {
                $this->ledgerPoster->postSimple(
                    type: SyariahTransactionTypes::SYB_TRADE_LC_ISSUE,
                    reference: "lc:margin:{$lc->id}",
                    description: "Setoran margin jaminan LC syariah {$lcNumber}",
                    entries: [
                        ['account_code' => 'syb:cash_pool:IDR', 'amount' => $marginDepositCents],
                        ['account_code' => 'syb:trade_lc_margin_deposit:IDR', 'amount' => -$marginDepositCents],
                    ]
                );
            }

            // Ujrah fee wakalah: Debit cash pool, Kredit syb:trade_ujrah_fee_income:IDR
            if ($wakalahFeeCents > 0) {
                $this->ledgerPoster->postSimple(
                    type: SyariahTransactionTypes::SYB_TRADE_UJRAH_FEE,
                    reference: "lc:ujrah:{$lc->id}",
                    description: "Pendapatan fee keagenan wakalah bil ujrah LC {$lcNumber}",
                    entries: [
                        ['account_code' => 'syb:cash_pool:IDR', 'amount' => $wakalahFeeCents],
                        ['account_code' => 'syb:trade_ujrah_fee_income:IDR', 'amount' => -$wakalahFeeCents],
                    ]
                );
            }

            SybTradeAuditLog::create([
                'audit_ref' => $lcNumber,
                'event_name' => 'LC_ISSUED',
                'details' => "Penerbitan LC syariah nominal {$amountCurrency} {$amountCents} dengan margin {$marginDepositCents}",
                'shariah_compliance_status' => 'COMPLIANT',
            ]);

            return $lc;
        });
    }

    public function createIstisnaOrder(
        int $lcId,
        string $manufacturerPartyRef,
        string $specificationSummary,
        int $totalPriceCents,
        int $budgetedCostCents
    ): SybIstisnaContract {
        $lc = SybLetterOfCredit::findOrFail($lcId);

        if ($totalPriceCents <= 0 || $budgetedCostCents <= 0) {
            throw new InvalidArgumentException('Harga dan anggaran biaya istisna harus bernilai positif.');
        }

        return DB::transaction(function () use (
            $lc,
            $manufacturerPartyRef,
            $specificationSummary,
            $totalPriceCents,
            $budgetedCostCents
        ) {
            $count = SybIstisnaContract::count() + 1;
            $contractNumber = sprintf('IST-%s-%04d', date('Ym'), $count);

            return SybIstisnaContract::create([
                'lc_id' => $lc->id,
                'contract_number' => $contractNumber,
                'manufacturer_party_ref' => $manufacturerPartyRef,
                'specification_summary' => $specificationSummary,
                'total_price_cents' => $totalPriceCents,
                'budgeted_cost_cents' => $budgetedCostCents,
                'actual_cost_incurred_cents' => 0,
                'recognized_revenue_cents' => 0,
                'progress_percent' => 0,
                'status' => IslamicTradeStatus::ACTIVE->value,
            ]);
        });
    }

    public function recordCostIncurred(
        int $istisnaId,
        int $costIncurredCents,
        string $inspectionNote,
        ?string $verifierRef = 'officer-trade-inspector'
    ): SybIstisnaContract {
        if ($costIncurredCents <= 0) {
            throw new InvalidArgumentException('Biaya aktual yang dicatat harus bernilai positif.');
        }

        SyariahAccounts::ensureAccountsExist();

        return DB::transaction(function () use ($istisnaId, $costIncurredCents, $inspectionNote, $verifierRef) {
            $istisna = SybIstisnaContract::findOrFail($istisnaId);

            $newActualCost = $istisna->actual_cost_incurred_cents + $costIncurredCents;
            // Cost-to-cost percentage of completion
            $progressPercent = (int) min(100, intdiv($newActualCost * 100, $istisna->budgeted_cost_cents));
            $newRecognizedRevenue = (int) intdiv($istisna->total_price_cents * $progressPercent, 100);
            $revenueIncrement = max(0, $newRecognizedRevenue - $istisna->recognized_revenue_cents);

            $milestoneName = sprintf('Milestone Progress %d%%', $progressPercent);

            SybIstisnaMilestone::create([
                'istisna_id' => $istisna->id,
                'milestone_name' => $milestoneName,
                'cost_incurred_cents' => $costIncurredCents,
                'progress_percentage' => $progressPercent,
                'inspection_note' => $inspectionNote,
                'verified_at' => CarbonImmutable::now(),
                'verifier_ref' => $verifierRef,
            ]);

            $istisna->update([
                'actual_cost_incurred_cents' => $newActualCost,
                'progress_percent' => $progressPercent,
                'recognized_revenue_cents' => $newRecognizedRevenue,
                'status' => $progressPercent >= 100 ? IslamicTradeStatus::COMPLETED->value : IslamicTradeStatus::ACTIVE->value,
            ]);

            // Ledger posting pengakuan WIP dan pendapatan berbasis POC
            if ($costIncurredCents > 0) {
                $this->ledgerPoster->postSimple(
                    type: SyariahTransactionTypes::SYB_ISTISNA_POC_RECOG,
                    reference: "istisna:wip:{$istisna->id}:{$progressPercent}",
                    description: "Pencatatan biaya pekerjaan manufaktur istisna {$istisna->contract_number} ({$progressPercent}%)",
                    entries: [
                        ['account_code' => 'syb:istisna_work_in_progress:IDR', 'amount' => $costIncurredCents],
                        ['account_code' => 'syb:cash_pool:IDR', 'amount' => -$costIncurredCents],
                    ]
                );
            }

            if ($revenueIncrement > 0) {
                $this->ledgerPoster->postSimple(
                    type: SyariahTransactionTypes::SYB_ISTISNA_POC_RECOG,
                    reference: "istisna:rev:{$istisna->id}:{$progressPercent}",
                    description: "Pengakuan pendapatan kontrak istisna {$istisna->contract_number} ({$progressPercent}%)",
                    entries: [
                        ['account_code' => 'syb:cash_pool:IDR', 'amount' => $revenueIncrement],
                        ['account_code' => 'syb:istisna_contract_revenue:IDR', 'amount' => -$revenueIncrement],
                    ]
                );
            }

            return $istisna->fresh();
        });
    }

    public function settleLetterOfCredit(int $lcId, string $settlementChannel = 'FIAT_IDR'): SybTradeSettlement
    {
        $lc = SybLetterOfCredit::with('istisnaContracts')->findOrFail($lcId);

        return DB::transaction(function () use ($lc, $settlementChannel) {
            $count = SybTradeSettlement::count() + 1;
            $settlementNumber = sprintf('STL-LC-%s-%04d', date('Ym'), $count);

            $settlement = SybTradeSettlement::create([
                'settlement_number' => $settlementNumber,
                'trade_contract_type' => IslamicTradeContractType::ISTISNA->value,
                'contract_reference' => $lc->lc_number,
                'payer_party_ref' => $lc->applicant_party_ref,
                'payee_party_ref' => $lc->beneficiary_party_ref,
                'amount_cents' => $lc->amount_cents,
                'currency' => $lc->amount_currency,
                'ujrah_fee_cents' => $lc->wakalah_fee_cents,
                'settlement_channel' => $settlementChannel,
                'settled_at' => CarbonImmutable::now(),
            ]);

            $lc->update(['status' => IslamicTradeStatus::COMPLETED->value]);

            return $settlement;
        });
    }
}
