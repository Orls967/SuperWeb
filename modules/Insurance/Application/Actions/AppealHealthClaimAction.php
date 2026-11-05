<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\HealthClaim;

final class AppealHealthClaimAction
{
    public function __construct(
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Mengajukan dan memutuskan banding atas klaim kesehatan yang ditolak (Fase 159.2).
     */
    public function execute(
        string $claimNumber,
        string $appealReason,
        string $medicalDirectorReview,
        string $decision,
        int $adjustedApprovedAmountIdr,
        string $actorId,
        string $idempotencyKey,
    ): HealthClaim {
        $claim = HealthClaim::where('claim_number', $claimNumber)->firstOrFail();
        $isApproved = ($decision === 'APPROVE');

        return $this->guard->run(
            key: $idempotencyKey,
            scope: 'ins:claim:appeal:'.$claimNumber,
            payload: [
                'claim_number' => $claimNumber,
                'appeal_reason' => $appealReason,
                'decision' => $decision,
                'adjusted_amount' => $adjustedApprovedAmountIdr,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use ($claim, $appealReason, $medicalDirectorReview, $isApproved, $adjustedApprovedAmountIdr) {
                return DB::transaction(function () use ($claim, $appealReason, $medicalDirectorReview, $isApproved, $adjustedApprovedAmountIdr) {
                    if ($isApproved) {
                        if ($adjustedApprovedAmountIdr <= 0 || $adjustedApprovedAmountIdr > $claim->incurred_amount_idr) {
                            throw new InvalidArgumentException('Nominal banding yang disetujui tidak valid atau melebihi tagihan awal.');
                        }

                        $patientShare = max(0, $claim->incurred_amount_idr - $adjustedApprovedAmountIdr);
                        $claim->update([
                            'status' => 'settled',
                            'approved_amount_idr' => $adjustedApprovedAmountIdr,
                            'patient_share_idr' => $patientShare,
                            'adjudication_notes' => "Banding Diterima: {$medicalDirectorReview} (Alasan Pemohon: {$appealReason})",
                        ]);
                    } else {
                        $claim->update([
                            'status' => 'rejected',
                            'adjudication_notes' => "Banding Ditolak: {$medicalDirectorReview} (Alasan Pemohon: {$appealReason})",
                        ]);
                    }

                    return $claim;
                });
            }
        );
    }
}
