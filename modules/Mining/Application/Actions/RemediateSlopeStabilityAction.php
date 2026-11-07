<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\GeotechIncidentStatus;
use Modules\Mining\Domain\Models\MiningGeotechIncident;
use Modules\Mining\Domain\StateMachines\GeotechIncidentStateMachine;
use Modules\Mining\Ledger\MiningFase119LedgerPostingService;

/**
 * Domain Action: Eksekusi Tindakan Remediasi Rekayasa Kestabilan Lereng Tambang.
 * Menerapkan penstabilan lereng (buttress penahan kaki, unloading mahkota lereng,
 * lubang drainase horizontal, atau perkuatan cable bolting) serta mencatat biaya remediasi ke ledger.
 */
class RemediateSlopeStabilityAction
{
    public function __construct(
        private readonly GeotechIncidentStateMachine $stateMachine,
        private readonly MiningFase119LedgerPostingService $ledgerService
    ) {}

    /**
     * @param  array{
     *     geotech_incident_id: string,
     *     remediation_type: string,
     *     contractor_name: string,
     *     estimated_cost_idr: float,
     *     earthwork_volume_m3?: float,
     *     drain_boreholes_count?: int,
     *     cable_bolts_count?: int,
     *     target_factor_of_safety: float
     * } $data
     * @return array{
     *     incident_code: string,
     *     remediation_type: string,
     *     status: string,
     *     remediation_started_at: string,
     *     ledger_journal_uuid?: string|null
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $incident = MiningGeotechIncident::query()->findOrFail($data['geotech_incident_id']);

        $currentStatus = GeotechIncidentStatus::tryFrom($incident->status) ?? GeotechIncidentStatus::MONITORING_NORMAL;
        $targetStatus = GeotechIncidentStatus::REMEDIATED;

        if ($this->stateMachine->canTransition($currentStatus, $targetStatus)) {
            $incident->status = $targetStatus->value;
        }

        $costIdr = (int) round((float) $data['estimated_cost_idr']);
        $journalUuid = null;

        if ($costIdr > 0) {
            $journalUuid = $this->ledgerService->postGeotechRemediationCost(
                referenceNumber: 'GEO-REM-'.$incident->incident_code,
                incidentId: $incident->id,
                amountIdr: $costIdr,
                remediationType: $data['remediation_type'],
                targetFos: (float) $data['target_factor_of_safety'],
                description: 'Biaya rekayasa remediasi lereng '.$incident->location_sector
            );
        }

        $meta = $incident->metadata ?? [];
        $meta['remediation_plan'] = [
            'type' => strtoupper(trim($data['remediation_type'])),
            'contractor' => $data['contractor_name'],
            'estimated_cost_idr' => $costIdr,
            'earthwork_m3' => (float) ($data['earthwork_volume_m3'] ?? 0.0),
            'drain_boreholes' => (int) ($data['drain_boreholes_count'] ?? 0),
            'cable_bolts' => (int) ($data['cable_bolts_count'] ?? 0),
            'target_fos' => (float) $data['target_factor_of_safety'],
            'remediation_started_at' => now()->toIso8601String(),
        ];
        $incident->metadata = $meta;
        $incident->save();

        return [
            'incident_code' => $incident->incident_code,
            'remediation_type' => strtoupper(trim($data['remediation_type'])),
            'status' => $incident->status,
            'remediation_started_at' => now()->toIso8601String(),
            'ledger_journal_uuid' => $journalUuid,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['geotech_incident_id'])) {
            throw new InvalidArgumentException('Geotech incident ID wajib diisi.');
        }

        if (empty($data['remediation_type'])) {
            throw new InvalidArgumentException('Tipe tindakan remediasi lereng wajib diisi.');
        }

        if (($data['target_factor_of_safety'] ?? 0) < 1.30) {
            throw new InvalidArgumentException('Target Faktor Keamanan (FoS) pasca-remediasi minimal harus 1.30 sesuai standar ESDM.');
        }
    }
}
