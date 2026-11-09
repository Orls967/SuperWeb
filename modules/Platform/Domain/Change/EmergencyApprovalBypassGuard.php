<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use Modules\Platform\Domain\Models\ChangeRequest;

final class EmergencyApprovalBypassGuard
{
    /**
     * Assesses whether an emergency bypass can be legally invoked for active P1 outage mitigation.
     *
     * @param  array{active_p1_incident_id: string, incident_commander_id: int, verbal_executive_approval: bool}  $emergencyContext
     * @return array{can_bypass: bool, reason: string, post_incident_requirements: list<string>}
     */
    public function assessEmergencyBypass(ChangeRequest $cr, array $emergencyContext): array
    {
        if ($cr->change_type !== 'emergency') {
            return [
                'can_bypass' => false,
                'reason' => 'Hanya tiket bertipe emergency yang dapat mengajukan CAB expedited bypass.',
                'post_incident_requirements' => [],
            ];
        }

        $hasIncidentId = ! empty($emergencyContext['active_p1_incident_id'] ?? '');
        $hasCommander = ($emergencyContext['incident_commander_id'] ?? 0) > 0;
        $hasExecApproval = (bool) ($emergencyContext['verbal_executive_approval'] ?? false);

        $canBypass = ($hasIncidentId && $hasCommander && $hasExecApproval);

        $requirements = [
            'Laporan retrospektif wajib disubmit dalam 48 jam.',
            'Audit log post-mortem ditandatangani oleh Incident Commander.',
            'Pencatatan nomor insiden P1 pada metadata change request.',
        ];

        return [
            'can_bypass' => $canBypass,
            'reason' => $canBypass ? 'Bypass darurat disetujui untuk pemulihan insiden P1 aktif.' : 'Prasyarat bypass darurat tidak lengkap.',
            'post_incident_requirements' => $requirements,
        ];
    }
}
