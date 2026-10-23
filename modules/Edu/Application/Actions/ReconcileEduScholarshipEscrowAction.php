<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Edu\Domain\Models\EduScholarshipEscrow;
use Modules\Shared\Application\BaseAction;
use RuntimeException;

class ReconcileEduScholarshipEscrowAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function execute(string $escrowId, int $disburseMinor): EduScholarshipEscrow
    {
        $escrow = EduScholarshipEscrow::findOrFail($escrowId);

        if ($escrow->status === 'CLOSED') {
            throw new RuntimeException("Escrow [{$escrow->escrow_code}] is closed.");
        }

        if ($escrow->disbursed_minor + $disburseMinor > $escrow->total_escrow_minor) {
            throw new RuntimeException("Disbursement exceeds total scholarship escrow pool [{$escrow->escrow_code}].");
        }

        return DB::transaction(function () use ($escrow, $disburseMinor) {
            $escrow->disbursed_minor += $disburseMinor;
            $escrow->allocated_minor = min($escrow->total_escrow_minor, $escrow->allocated_minor + $disburseMinor);

            if ($escrow->disbursed_minor >= $escrow->total_escrow_minor) {
                $escrow->status = 'RECONCILED';
            }
            $escrow->save();

            if ($disburseMinor > 0) {
                $idempotencyKey = hash('sha256', "SCHOLARSHIP-REC:{$escrow->escrow_code}:{$escrow->disbursed_minor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'EDU_SCHOLARSHIP_DISBURSE',
                    description: "Scholarship escrow disbursement for {$escrow->escrow_code}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('edu:escrow_liability:IDR', $disburseMinor)
                        ->credit('edu:tuition_revenue:IDR', $disburseMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EDU_SCHOLARSHIP_ESCROW',
                    referenceId: $escrow->escrow_code,
                ));
            }

            $this->audit(
                action: 'edu_scholarship_reconciled',
                auditable: $escrow,
                newValues: ['disbursed' => $disburseMinor, 'new_status' => $escrow->status],
                impactType: 'financial',
            );

            return $escrow->fresh();
        });
    }
}
