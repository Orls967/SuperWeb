<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Audit;

use Modules\Platform\Domain\Models\ChangeRequest;

final class ChangeRequestAuditValidator
{
    /**
     * Audits all change requests in database for structural integrity violations.
     *
     * @return list<string> list of found integrity violations
     */
    public function auditAll(): array
    {
        $violations = [];
        $changes = ChangeRequest::with('rollbackPlan')->get();

        foreach ($changes as $cr) {
            // Invariant 1: High risk must have passed cab_review before approval
            if (in_array($cr->status, ['approved', 'scheduled', 'deploying', 'verified', 'closed'], true)) {
                if ($cr->risk_score > 6500) {
                    $hasCabReview = $cr->transitions()->where('to_status', 'cab_review')->exists();
                    if (! $hasCabReview) {
                        $violations[] = "Change Request {$cr->reference_number} berisiko tinggi ({$cr->risk_score}) disetujui tanpa rekam jejak CAB review.";
                    }
                }

                // Invariant 2: Must have rollback plan
                if ($cr->rollbackPlan === null) {
                    $violations[] = "Change Request {$cr->reference_number} disetujui tanpa ChangeRollbackPlan terdaftar.";
                }
            }

            // Invariant 3: Score and tier alignment
            if ($cr->risk_score <= 3000 && $cr->risk_tier !== 'low') {
                $violations[] = "Change Request {$cr->reference_number} memiliki inkonsistensi skor {$cr->risk_score} dengan tier {$cr->risk_tier}.";
            }
        }

        return $violations;
    }
}
