<?php

declare(strict_types=1);

namespace Modules\Syariah\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Domain\Algorithms\Islamic\RestructuringSchedule;
use Modules\Shared\Domain\Algorithms\Islamic\RestructuringScheduleCertificateVerifier;
use Modules\Syariah\Domain\Models\SybRestructuredInstallment;
use Modules\Syariah\Domain\Models\SybRestructuringRequest;
use Modules\Syariah\Ledger\SyariahAccounts;
use Modules\Syariah\Ledger\SyariahTransactionTypes;
use RuntimeException;

final class SyariahRestructuringService
{
    public function __construct(
        private readonly ShariahLedgerPoster $ledgerPoster,
    ) {}

    /**
     * Mengajukan restrukturisasi pembiayaan syariah bermasalah.
     */
    public function requestRestructuring(
        string $financingRef,
        string $customerRef,
        string $contractType,
        int $originalOutstandingPrincipalMinor,
        int $originalRemainingMarginMinor,
        int $marginHaircutBps,
        int $originalTenorMonths,
        int $newTenorMonths,
        int $gracePeriodMonths,
        string $reason,
        string $requestedBy
    ): SybRestructuringRequest {
        $scheduleResult = RestructuringSchedule::calculate(
            financingRef: $financingRef,
            originalOutstandingPrincipalMinor: $originalOutstandingPrincipalMinor,
            originalRemainingMarginMinor: $originalRemainingMarginMinor,
            marginHaircutBps: $marginHaircutBps,
            newTenorMonths: $newTenorMonths,
            gracePeriodMonths: $gracePeriodMonths
        );

        RestructuringScheduleCertificateVerifier::verify($scheduleResult);

        $requestRef = 'RST-SYB-'.hash('sha256', $financingRef.':'.microtime());
        $requestRef = substr($requestRef, 0, 32);

        return SybRestructuringRequest::create([
            'request_ref' => $requestRef,
            'financing_ref' => $financingRef,
            'customer_ref' => $customerRef,
            'contract_type' => $contractType,
            'original_principal_minor' => $originalOutstandingPrincipalMinor,
            'original_margin_minor' => $originalRemainingMarginMinor,
            'margin_haircut_bps' => $marginHaircutBps,
            'revised_principal_minor' => $scheduleResult->revisedPrincipalMinor,
            'revised_margin_minor' => $scheduleResult->revisedMarginMinor,
            'total_restructured_debt_minor' => $scheduleResult->totalRestructuredDebtMinor,
            'original_tenor_months' => $originalTenorMonths,
            'new_tenor_months' => $newTenorMonths,
            'grace_period_months' => $gracePeriodMonths,
            'status' => 'REQUESTED',
            'reason' => $reason,
            'requested_by' => $requestedBy,
            'certificate_hash' => $scheduleResult->certificateHash,
        ]);
    }

    /**
     * Penelaahan dan persetujuan kepatuhan syariah oleh Dewan Pengawas Syariah (DPS).
     */
    public function reviewByDps(string $requestRef, string $dpsReviewerRef, string $notes, bool $isApproved): SybRestructuringRequest
    {
        $req = SybRestructuringRequest::where('request_ref', $requestRef)->firstOrFail();

        if ($req->status !== 'REQUESTED') {
            throw new RuntimeException("Cannot review restructuring request in status {$req->status}.");
        }

        $req->update([
            'dps_reviewer_ref' => $dpsReviewerRef,
            'dps_notes' => $notes,
            'status' => $isApproved ? 'DPS_REVIEWED' : 'REJECTED',
        ]);

        return $req->fresh();
    }

    /**
     * Persetujuan komite pembiayaan (Four-Eyes Approval).
     */
    public function approve(string $requestRef): SybRestructuringRequest
    {
        $req = SybRestructuringRequest::where('request_ref', $requestRef)->firstOrFail();

        if ($req->status !== 'DPS_REVIEWED') {
            throw new RuntimeException("Cannot approve restructuring request before DPS review (current status: {$req->status}).");
        }

        $req->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
        ]);

        return $req->fresh();
    }

    /**
     * Eksekusi restrukturisasi: menerbitkan jadwal cicilan baru dan memposting ledger.
     */
    public function execute(string $requestRef): SybRestructuringRequest
    {
        $req = SybRestructuringRequest::where('request_ref', $requestRef)->firstOrFail();

        if ($req->status !== 'APPROVED') {
            throw new RuntimeException("Cannot execute restructuring request in status {$req->status}.");
        }

        return DB::transaction(function () use ($req) {
            SyariahAccounts::ensureAccountsExist();

            // Hitung ulang jadwal menggunakan Algoritma B9
            $scheduleResult = RestructuringSchedule::calculate(
                financingRef: $req->financing_ref,
                originalOutstandingPrincipalMinor: $req->original_principal_minor,
                originalRemainingMarginMinor: $req->original_margin_minor,
                marginHaircutBps: $req->margin_haircut_bps,
                newTenorMonths: $req->new_tenor_months,
                gracePeriodMonths: $req->grace_period_months
            );

            RestructuringScheduleCertificateVerifier::verify($scheduleResult);

            // Tulis baris cicilan baru
            $baseDate = Carbon::now()->startOfMonth();
            foreach ($scheduleResult->scheduleTable as $row) {
                $dueDate = (clone $baseDate)->addMonths($row['month']);

                SybRestructuredInstallment::create([
                    'request_ref' => $req->request_ref,
                    'month_number' => $row['month'],
                    'is_grace' => $row['is_grace'],
                    'principal_minor' => $row['principal_minor'],
                    'margin_minor' => $row['margin_minor'],
                    'total_installment_minor' => $row['installment_minor'],
                    'remaining_principal_minor' => $row['remaining_principal_minor'],
                    'remaining_margin_minor' => $row['remaining_margin_minor'],
                    'due_date' => $dueDate->toDateString(),
                    'payment_status' => $row['is_grace'] ? 'WAIVED' : 'PENDING',
                ]);
            }

            // Posting ledger: mutasi sisa pokok lama ke akun pembiayaan yang direstrukturisasi
            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_RESTRUCTURING_RESCHEDULE,
                reference: $req->request_ref,
                description: "Eksekusi restrukturisasi pembiayaan syariah {$req->financing_ref} tenor baru {$req->new_tenor_months} bulan",
                entries: [
                    [
                        'account_code' => 'syb:restructured_financing:IDR',
                        'amount' => $scheduleResult->revisedPrincipalMinor,
                    ],
                    [
                        'account_code' => 'syb:micro_financing_receivable:IDR',
                        'amount' => -$scheduleResult->revisedPrincipalMinor,
                    ],
                ]
            );

            $req->update([
                'status' => 'EXECUTED',
                'executed_at' => now(),
            ]);

            return $req->fresh();
        });
    }

    /**
     * Membayar cicilan restrukturisasi bulanan.
     */
    public function payInstallment(string $requestRef, int $monthNumber): SybRestructuredInstallment
    {
        $inst = SybRestructuredInstallment::where('request_ref', $requestRef)
            ->where('month_number', $monthNumber)
            ->firstOrFail();

        if ($inst->payment_status === 'PAID') {
            return $inst;
        }

        if ($inst->is_grace) {
            throw new RuntimeException("Month {$monthNumber} is in grace period with no installment due.");
        }

        return DB::transaction(function () use ($inst) {
            SyariahAccounts::ensureAccountsExist();

            $entries = [
                [
                    'account_code' => 'syb:wallet_float:IDR',
                    'amount' => $inst->total_installment_minor,
                ],
                [
                    'account_code' => 'syb:restructured_financing:IDR',
                    'amount' => -$inst->principal_minor,
                ],
            ];

            if ($inst->margin_minor > 0) {
                $entries[] = [
                    'account_code' => 'syb:ujrah_income:IDR',
                    'amount' => -$inst->margin_minor,
                ];
            }

            $journalRef = 'syb:post:'.hash('sha256', SyariahTransactionTypes::SYB_RESTRUCTURED_INSTALLMENT->value.":{$inst->request_ref}-M{$inst->month_number}");

            $this->ledgerPoster->postSimple(
                type: SyariahTransactionTypes::SYB_RESTRUCTURED_INSTALLMENT,
                reference: "{$inst->request_ref}-M{$inst->month_number}",
                description: "Pembayaran cicilan restrukturisasi {$inst->request_ref} bulan ke-{$inst->month_number}",
                entries: $entries
            );

            $inst->update([
                'payment_status' => 'PAID',
                'paid_at' => now(),
                'ledger_journal_ref' => $journalRef,
            ]);

            return $inst->fresh();
        });
    }
}
