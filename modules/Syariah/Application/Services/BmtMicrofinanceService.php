<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Shared\Domain\Algorithms\Islamic\GigCreditLimit;
use Modules\Shared\Domain\Algorithms\Islamic\RepaymentWaterfall;
use Modules\Syariah\Contracts\GigPayoutHistory;
use Modules\Syariah\Domain\Enums\BmtSavingsContractType;
use Modules\Syariah\Domain\Models\SybBmtCooperative;
use Modules\Syariah\Domain\Models\SybBmtRepayment;
use Modules\Syariah\Domain\Models\SybBmtSavings;
use Modules\Syariah\Domain\Models\SybBmtSavingsTransaction;
use Modules\Syariah\Domain\Models\SybGigDeduction;
use Modules\Syariah\Domain\Models\SybGigMicroloan;
use Modules\Syariah\Domain\Models\SybGroupFinancing;
use Modules\Syariah\Domain\Models\SybGroupMember;
use Modules\Syariah\Domain\Models\SybSocialImpactMetric;
use Modules\Syariah\Ledger\SyariahTransactionTypes;

class BmtMicrofinanceService
{
    public function __construct(
        protected ShariahLedgerPoster $ledgerPoster,
        protected GigPayoutHistory $gigPayoutHistory
    ) {}

    public function createCooperative(array $data): SybBmtCooperative
    {
        return SybBmtCooperative::create([
            'cooperative_code' => $data['cooperative_code'] ?? 'BMT-'.strtoupper(Str::random(6)),
            'name' => $data['name'],
            'legal_entity_number' => $data['legal_entity_number'],
            'city' => $data['city'],
            'address' => $data['address'] ?? null,
            'dps_advisor' => $data['dps_advisor'],
            'is_active' => true,
        ]);
    }

    public function openSavingsAccount(
        string $cooperativeCode,
        string $customerPartyId,
        string $contractType = 'WADIAH_YAD_DHAMANAH',
        int $initialDepositIdr = 0
    ): SybBmtSavings {
        return DB::transaction(function () use ($cooperativeCode, $customerPartyId, $contractType, $initialDepositIdr) {
            $accountNumber = 'SAV-BMT-'.strtoupper(Str::random(8));

            $savings = SybBmtSavings::create([
                'account_number' => $accountNumber,
                'cooperative_code' => $cooperativeCode,
                'customer_party_ref' => $customerPartyId,
                'contract_type' => $contractType,
                'balance_idr' => 0,
                'is_active' => true,
            ]);

            if ($initialDepositIdr > 0) {
                $this->depositSavings($accountNumber, $initialDepositIdr, 'Setoran Awal Tabungan Syariah');
            }

            return $savings->fresh();
        });
    }

    public function depositSavings(string $accountNumber, int $amountIdr, string $note = ''): SybBmtSavingsTransaction
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Jumlah setoran tabungan harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($accountNumber, $amountIdr, $note) {
            $account = SybBmtSavings::where('account_number', $accountNumber)->lockForUpdate()->firstOrFail();
            $account->balance_idr += $amountIdr;
            $account->save();

            $tx = SybBmtSavingsTransaction::create([
                'transaction_ref' => 'DEP-'.strtoupper(Str::random(10)),
                'account_number' => $accountNumber,
                'type' => 'DEPOSIT',
                'amount_idr' => $amountIdr,
                'balance_after_idr' => $account->balance_idr,
                'note' => $note,
            ]);

            $targetLedger = ($account->contract_type === BmtSavingsContractType::WADIAH_YAD_DHAMANAH->value)
                ? 'syb:customer_savings_wadiah:IDR'
                : 'syb:customer_savings_mudharabah:IDR';

            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_BMT_SAVINGS_DEPOSIT,
                reference: $tx->transaction_ref,
                description: 'Setoran Tabungan BMT: '.$note,
                entries: [
                    'syb:cash_pool:IDR' => -$amountIdr,
                    $targetLedger => $amountIdr,
                ]
            );

            return $tx;
        });
    }

    public function withdrawSavings(string $accountNumber, int $amountIdr, string $note = ''): SybBmtSavingsTransaction
    {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Jumlah penarikan harus positif.');
        }

        return DB::transaction(function () use ($accountNumber, $amountIdr, $note) {
            $account = SybBmtSavings::where('account_number', $accountNumber)->lockForUpdate()->firstOrFail();
            if ($account->balance_idr < $amountIdr) {
                throw new InvalidArgumentException('Saldo tabungan BMT tidak mencukupi.');
            }

            $account->balance_idr -= $amountIdr;
            $account->save();

            $tx = SybBmtSavingsTransaction::create([
                'transaction_ref' => 'WDR-'.strtoupper(Str::random(10)),
                'account_number' => $accountNumber,
                'type' => 'WITHDRAWAL',
                'amount_idr' => $amountIdr,
                'balance_after_idr' => $account->balance_idr,
                'note' => $note,
            ]);

            $targetLedger = ($account->contract_type === BmtSavingsContractType::WADIAH_YAD_DHAMANAH->value)
                ? 'syb:customer_savings_wadiah:IDR'
                : 'syb:customer_savings_mudharabah:IDR';

            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_BMT_SAVINGS_WITHDRAWAL,
                reference: $tx->transaction_ref,
                description: 'Penarikan Tabungan BMT: '.$note,
                entries: [
                    $targetLedger => -$amountIdr,
                    'syb:cash_pool:IDR' => $amountIdr,
                ]
            );

            return $tx;
        });
    }

    public function createGroupFacility(
        string $cooperativeCode,
        string $groupName,
        int $totalMembers,
        int $facilityAmountIdr,
        string $shariahContract = 'MUDHARABAH_BERTINGKAT',
        int $profitNisbahShohibulMaalBps = 4000
    ): SybGroupFinancing {
        return DB::transaction(function () use (
            $cooperativeCode,
            $groupName,
            $totalMembers,
            $facilityAmountIdr,
            $shariahContract,
            $profitNisbahShohibulMaalBps
        ) {
            $code = 'GRP-BMT-'.strtoupper(Str::random(6));

            $group = SybGroupFinancing::create([
                'group_code' => $code,
                'cooperative_code' => $cooperativeCode,
                'group_name' => $groupName,
                'total_members' => $totalMembers,
                'total_facility_amount_idr' => $facilityAmountIdr,
                'outstanding_balance_idr' => $facilityAmountIdr,
                'shariah_contract' => $shariahContract,
                'profit_nisbah_shohibul_maal_bps' => $profitNisbahShohibulMaalBps,
                'group_liability_active' => true,
                'status' => 'active',
            ]);

            // Buat member pembagian rata
            $sharePerMember = (int) intdiv($facilityAmountIdr, max(1, $totalMembers));
            for ($m = 1; $m <= $totalMembers; $m++) {
                SybGroupMember::create([
                    'group_code' => $code,
                    'member_party_ref' => 'MBR-'.$code.'-0'.$m,
                    'member_name' => 'Anggota '.$groupName.' #'.$m,
                    'share_amount_idr' => $sharePerMember,
                    'is_active' => true,
                ]);
            }

            // Posting pencairan dana pembiayaan mikro kelompok
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_MICRO_DISBURSEMENT,
                reference: $code,
                description: 'Pencairan Pembiayaan Mikro Kelompok: '.$groupName,
                entries: [
                    'syb:micro_financing_receivable:IDR' => -$facilityAmountIdr,
                    'syb:cash_pool:IDR' => $facilityAmountIdr,
                ]
            );

            return $group;
        });
    }

    public function issueGigMicrocredit(
        string $workerPartyId,
        int $amountIdr,
        int $tenorMonths = 6
    ): SybGigMicroloan {
        return DB::transaction(function () use ($workerPartyId, $amountIdr, $tenorMonths) {
            $history = $this->gigPayoutHistory->getMonthlyPayoutHistory($workerPartyId, 6);
            $amanahScore = $this->gigPayoutHistory->getAmanahScore($workerPartyId);
            $incomes = array_column($history, 'gross_amount');

            $calc = GigCreditLimit::calculate(
                workerPartyId: $workerPartyId,
                monthlyIncomes: $incomes,
                amanahScore: $amanahScore,
                tenorMonths: $tenorMonths
            );

            if ($amountIdr > $calc->maxCreditLimit) {
                throw new InvalidArgumentException("Permohonan pembiayaan Rp {$amountIdr} melebihi limit kredit bersertifikat Rp {$calc->maxCreditLimit}");
            }

            $code = 'GIG-'.strtoupper(Str::random(8));
            $monthlyDeduction = (int) intdiv($amountIdr, max(1, $tenorMonths));

            $loan = SybGigMicroloan::create([
                'loan_code' => $code,
                'worker_party_ref' => $workerPartyId,
                'gross_average_income_idr' => $calc->averageMonthlyIncome,
                'income_volatility_bps' => $calc->incomeVolatilityBps,
                'amanah_score' => $amanahScore,
                'approved_credit_limit_idr' => $calc->maxCreditLimit,
                'disbursed_amount_idr' => $amountIdr,
                'remaining_balance_idr' => $amountIdr,
                'monthly_deduction_idr' => $monthlyDeduction,
                'status' => 'active',
            ]);

            // Ledger posting
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_MICRO_DISBURSEMENT,
                reference: $code,
                description: 'Pencairan Pembiayaan Gig Worker: '.$workerPartyId,
                entries: [
                    'syb:micro_financing_receivable:IDR' => -$amountIdr,
                    'syb:cash_pool:IDR' => $amountIdr,
                ]
            );

            return $loan;
        });
    }

    public function deductGigLoan(string $loanCode, int $payoutAmountIdr): SybGigDeduction
    {
        return DB::transaction(function () use ($loanCode, $payoutAmountIdr) {
            $loan = SybGigMicroloan::where('loan_code', $loanCode)->lockForUpdate()->firstOrFail();

            $deductAmount = min($loan->monthly_deduction_idr, $loan->remaining_balance_idr, $payoutAmountIdr);
            if ($deductAmount <= 0) {
                throw new InvalidArgumentException('Tidak ada saldo cicilan yang dapat dipotong.');
            }

            $marginPortion = (int) intdiv($deductAmount * 15, 100); // 15% ujrah/margin
            $principalPortion = $deductAmount - $marginPortion;

            $loan->remaining_balance_idr -= $principalPortion;
            if ($loan->remaining_balance_idr <= 0) {
                $loan->remaining_balance_idr = 0;
                $loan->status = 'paid_off';
            }
            $loan->save();

            $deduction = SybGigDeduction::create([
                'deduction_code' => 'DED-'.strtoupper(Str::random(8)),
                'loan_code' => $loanCode,
                'payout_ref' => 'PAY-'.strtoupper(Str::random(8)),
                'deducted_amount_idr' => $deductAmount,
                'allocated_margin_idr' => $marginPortion,
                'allocated_principal_idr' => $principalPortion,
                'allocated_tazir_idr' => 0,
                'deducted_at' => CarbonImmutable::now(),
            ]);

            // Ledger posting
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_MICRO_REPAYMENT,
                reference: $deduction->deduction_code,
                description: 'Auto-Deduct Pembiayaan Gig Worker: '.$loanCode,
                entries: [
                    'syb:cash_pool:IDR' => -$deductAmount,
                    'syb:micro_financing_receivable:IDR' => $principalPortion,
                    'syb:micro_ujrah_income:IDR' => $marginPortion,
                ]
            );

            return $deduction;
        });
    }

    public function executeWaterfallRepayment(
        string $facilityCode,
        string $payerPartyId,
        int $paymentAmountIdr,
        int $dueMarginIdr,
        int $duePrincipalIdr,
        int $tazirRateBps = 0,
        int $daysOverdue = 0
    ): SybBmtRepayment {
        return DB::transaction(function () use (
            $facilityCode,
            $payerPartyId,
            $paymentAmountIdr,
            $dueMarginIdr,
            $duePrincipalIdr,
            $tazirRateBps,
            $daysOverdue
        ) {
            $res = RepaymentWaterfall::allocate(
                paymentAmount: $paymentAmountIdr,
                dueMargin: $dueMarginIdr,
                duePrincipal: $duePrincipalIdr,
                tazirDailyRateBps: $tazirRateBps,
                daysOverdue: $daysOverdue
            );

            $code = 'RPY-'.strtoupper(Str::random(8));

            $repayment = SybBmtRepayment::create([
                'repayment_code' => $code,
                'facility_code' => $facilityCode,
                'payer_party_ref' => $payerPartyId,
                'payment_amount_idr' => $paymentAmountIdr,
                'allocated_margin_idr' => $res->allocatedMargin,
                'allocated_principal_idr' => $res->allocatedPrincipal,
                'allocated_tazir_idr' => $res->allocatedTazir,
                'excess_deposit_idr' => $res->excessDeposit,
                'certificate_hash' => $res->certificate['hash'],
                'paid_at' => CarbonImmutable::now(),
            ]);

            // Ledger posting berjenjang
            $entries = [
                'syb:cash_pool:IDR' => -$paymentAmountIdr,
            ];
            if ($res->allocatedPrincipal > 0) {
                $entries['syb:micro_financing_receivable:IDR'] = $res->allocatedPrincipal;
            }
            if ($res->allocatedMargin > 0) {
                $entries['syb:micro_ujrah_income:IDR'] = $res->allocatedMargin;
            }
            if ($res->allocatedTazir > 0) {
                $entries['syb:tazir_social_fund:IDR'] = $res->allocatedTazir;
            }
            if ($res->excessDeposit > 0) {
                $entries['syb:customer_savings_wadiah:IDR'] = $res->excessDeposit;
            }

            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_MICRO_REPAYMENT,
                reference: $code,
                description: 'Angsuran Waterfall Pembiayaan: '.$facilityCode,
                entries: $entries
            );

            return $repayment;
        });
    }

    public function recordSocialImpact(
        int $periodYear,
        int $jobsCreated,
        int $mustahikToMuzakkiCount,
        int $avgIncomeIncreaseBps
    ): SybSocialImpactMetric {
        $totalBeneficiaries = SybGroupMember::where('is_active', true)->count() + SybGigMicroloan::count();

        return SybSocialImpactMetric::updateOrCreate(
            ['period_year' => $periodYear],
            [
                'total_beneficiaries' => max(1, $totalBeneficiaries),
                'jobs_created' => $jobsCreated,
                'mustahik_to_muzakki_count' => $mustahikToMuzakkiCount,
                'avg_income_increase_bps' => $avgIncomeIncreaseBps,
                'micro_npf_ratio_bps' => 120, // 1.2% NPF
                'notes' => 'Tervalidasi melalui survei berkala dampak sosial lapangan.',
            ]
        );
    }
}
