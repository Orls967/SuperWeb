<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\InterBranchClearing;
use Modules\Hospital\Domain\StateMachines\InterBranchClearingStateMachine;

/**
 * Layanan Kliring Finansial Antar-Cabang & Jaringan Laboratorium (Fase 105).
 */
class InterBranchLabClearingService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Membuat kewajiban kliring baru antar-cabang.
     */
    public function createClearingObligation(
        int $fromBranchId,
        int $toBranchId,
        string $referenceType,
        string $referenceId,
        int $amountIdr
    ): InterBranchClearing {
        $clearingCode = 'CLR-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));

        return InterBranchClearing::create([
            'clearing_code' => $clearingCode,
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'amount_idr' => $amountIdr,
            'settlement_status' => InterBranchClearingStateMachine::PENDING,
        ]);
    }

    /**
     * Mengeksekusi settlement finansial kewajiban kliring dengan penjurnalan berimbang.
     */
    public function settleClearing(InterBranchClearing $clearing): InterBranchClearing
    {
        return DB::transaction(function () use ($clearing) {
            $fresh = $clearing->fresh();

            if ($fresh->settlement_status === InterBranchClearingStateMachine::SETTLED) {
                return $fresh;
            }

            $journalCode = 'JRN-CLR-'.$fresh->clearing_code.'-'.time();
            $amount = $fresh->amount_idr;

            $this->ledgerService->post(new PostingDTO(
                type: 'INTER_BRANCH_CLEARING',
                description: "Inter-branch clearing settlement [{$fresh->clearing_code}] from branch {$fresh->from_branch_id} to {$fresh->to_branch_id}",
                idempotencyKey: "HSP-CLR-{$fresh->clearing_code}",
                entries: Posting::lines()
                    ->debit('hsp:epharmacy_receivable:IDR', $amount)
                    ->credit('hsp:inter_branch_clearing:IDR', $amount)
                    ->toEntries('IDR'),
                referenceType: 'INTER_BRANCH_CLEARING',
                referenceId: (string) $fresh->id,
            ));

            $fresh->update([
                'settlement_status' => InterBranchClearingStateMachine::SETTLED,
            ]);

            return $fresh;
        });
    }
}
