<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hospital\Domain\Models\InterBranchClearing;
use RuntimeException;

class InterBranchFinancialClearingService
{
    public function __construct(
        private readonly ?LedgerService $ledgerService = null,
    ) {}

    /**
     * Create an inter-branch settlement obligation
     */
    public function createClearingObligation(
        int $fromBranchId,
        int $toBranchId,
        string $referenceType,
        string $referenceId,
        int $amountIdr
    ): InterBranchClearing {
        if ($fromBranchId === $toBranchId) {
            throw new RuntimeException('Cannot create inter-branch clearing between identical branches.');
        }

        return InterBranchClearing::create([
            'clearing_code' => 'CLR-'.strtoupper(bin2hex(random_bytes(6))),
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'amount_idr' => $amountIdr,
            'settlement_status' => 'PENDING',
        ]);
    }

    /**
     * Settle clearing obligation with balanced double-entry ledger posting
     */
    public function settleClearing(InterBranchClearing $clearing): InterBranchClearing
    {
        if ($clearing->settlement_status === 'SETTLED') {
            return $clearing;
        }

        $ledger = $this->ledgerService ?? app(LedgerService::class);

        return DB::transaction(function () use ($clearing, $ledger) {
            $ledger->post(new PostingDTO(
                type: 'INTER_BRANCH_CLEARING',
                description: "Inter-branch financial clearing for {$clearing->clearing_code}",
                idempotencyKey: "HSP-CLEARING-{$clearing->clearing_code}",
                entries: Posting::lines()
                    ->debit('hsp:inter_branch_clearing:IDR', $clearing->amount_idr)
                    ->credit('hsp:epharmacy_receivable:IDR', $clearing->amount_idr)
                    ->toEntries('IDR'),
                referenceType: 'INTER_BRANCH_CLEARING',
                referenceId: (string) $clearing->id,
            ));

            $clearing->update([
                'settlement_status' => 'SETTLED',
            ]);

            return $clearing;
        });
    }
}
