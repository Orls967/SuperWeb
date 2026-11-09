<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use Modules\Platform\Domain\Models\ChangeRequest;

final class ChangeAuditComplianceVerifier
{
    /**
     * Verifies that all pre-requisites are verified before change is permitted to deploy.
     *
     * @return array{is_compliant: bool, checks: array<string, bool>, blocking_reasons: list<string>}
     */
    public function verifyCompliance(ChangeRequest $cr): array
    {
        $checks = [
            'has_valid_reference' => ! empty($cr->reference_number),
            'has_risk_assessment' => $cr->risk_score > 0,
            'has_rollback_plan' => $cr->rollbackPlan !== null,
            'approved_or_cab_cleared' => in_array($cr->status, ['approved', 'scheduled'], true),
            'target_module_defined' => ! empty($cr->target_module),
            'release_train_assigned' => $cr->release_train_id !== null,
        ];

        $blockingReasons = [];

        if (! $checks['has_rollback_plan']) {
            $blockingReasons[] = 'Rencana rollback (ChangeRollbackPlan) wajib dilampirkan sebelum perubahan dapat dijadwalkan.';
        }

        if (! $checks['has_risk_assessment']) {
            $blockingReasons[] = 'Penilaian risiko kuantitatif (A4) belum dijalankan pada tiket perubahan ini.';
        }

        if (! $checks['approved_or_cab_cleared']) {
            $blockingReasons[] = "Status tiket saat ini ({$cr->status}) belum disetujui untuk peluncuran.";
        }

        if (! $checks['release_train_assigned']) {
            $blockingReasons[] = 'Perubahan belum dimasukkan ke dalam gerbong rilis resmi (Release Train).';
        }

        $isCompliant = $blockingReasons === [];

        return [
            'is_compliant' => $isCompliant,
            'checks' => $checks,
            'blocking_reasons' => $blockingReasons,
        ];
    }
}
