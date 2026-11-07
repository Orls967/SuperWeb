<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Application\Actions\ReconcileMultiLineLoyaltyLiabilityAction;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelLoyaltyAuditDiscrepancyResolverService
{
    public function __construct(
        protected ?ReconcileMultiLineLoyaltyLiabilityAction $reconcileAction = null,
        protected ?LedgerService $ledgerService = null
    ) {
        $this->reconcileAction = $reconcileAction ?? app(ReconcileMultiLineLoyaltyLiabilityAction::class);
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     resolution_id: string,
     *     discrepancy_amount_idr: int,
     *     adjustment_direction: string,
     *     status: string,
     *     resolved_by: string,
     *     resolved_at: string
     * }
     */
    public function resolveAuditDiscrepancy(
        string $authorizedStaffReference,
        string $auditJustificationNote
    ): array {
        return DB::transaction(function () use ($authorizedStaffReference, $auditJustificationNote) {
            $recon = $this->reconcileAction->execute();

            if ($recon['is_balanced']) {
                throw new RuntimeException('Tidak ada selisih audit liabilitas yang perlu disesuaikan (saldo sudah seimbang).');
            }

            $diffIdr = abs((int) $recon['discrepancy_idr']);
            $resolutionId = 'RES-DISC-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3));

            // If internal database liability is greater than ledger, credit liability & debit expense
            // If internal database liability is less than ledger, debit liability & credit breakage/revenue
            $isInternalHigher = $recon['total_liability_idr'] > $recon['ledger_liability_balance_idr'];

            if ($isInternalHigher) {
                $this->ledgerService->post(new PostingDTO(
                    type: HotelTransactionTypes::TRAVEL_PASS_PTS_EARNED->value,
                    description: "Audit true-up adjustment: {$auditJustificationNote} by {$authorizedStaffReference}",
                    idempotencyKey: "HTL-ADJ-AUDIT-{$resolutionId}",
                    entries: Posting::lines()
                        ->debit('htl:loyalty_program_expense:IDR', $diffIdr)
                        ->credit('htl:loyalty_points_liability:IDR', $diffIdr)
                        ->toEntries('IDR'),
                    referenceType: 'AUDIT_DISCREPANCY_TRUE_UP',
                    referenceId: $resolutionId,
                ));
            } else {
                $this->ledgerService->post(new PostingDTO(
                    type: HotelTransactionTypes::HTL_LOYALTY_BREAKAGE->value,
                    description: "Audit true-up adjustment write-down: {$auditJustificationNote} by {$authorizedStaffReference}",
                    idempotencyKey: "HTL-ADJ-AUDIT-{$resolutionId}",
                    entries: Posting::lines()
                        ->debit('htl:loyalty_points_liability:IDR', $diffIdr)
                        ->credit('htl:loyalty_breakage_revenue:IDR', $diffIdr)
                        ->toEntries('IDR'),
                    referenceType: 'AUDIT_DISCREPANCY_TRUE_UP',
                    referenceId: $resolutionId,
                ));
            }

            return [
                'resolution_id' => $resolutionId,
                'discrepancy_amount_idr' => $diffIdr,
                'adjustment_direction' => $isInternalHigher ? 'EXPENSE_INCREASE' : 'BREAKAGE_RECOGNITION',
                'status' => 'RECONCILED_AND_BALANCED',
                'resolved_by' => $authorizedStaffReference,
                'resolved_at' => now()->toIso8601String(),
            ];
        });
    }
}
