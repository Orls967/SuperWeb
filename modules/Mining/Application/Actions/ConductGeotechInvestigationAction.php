<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\GeotechIncidentStatus;
use Modules\Mining\Domain\Models\MiningGeotechIncident;
use Modules\Mining\Domain\StateMachines\GeotechIncidentStateMachine;

/**
 * Domain Action: Investigasi Rekayasa Geoteknik Terhadap Deformasi Lereng Tambang.
 * Melakukan karakterisasi rekahan, analisis bidang gelincir diskontinuitas (dip/dip direction),
 * dan back-analysis kekuatan geser batuan pasca-pergerakan massa.
 */
class ConductGeotechInvestigationAction
{
    public function __construct(
        private readonly GeotechIncidentStateMachine $stateMachine
    ) {}

    /**
     * @param  array{
     *     geotech_incident_id: string,
     *     investigator_name: string,
     *     failure_mechanism: string,
     *     failure_volume_m3_estimate: float,
     *     dip_direction_degrees: float,
     *     dip_angle_degrees: float,
     *     water_condition: string,
     *     findings_summary: string
     * } $data
     * @return array{
     *     incident_code: string,
     *     failure_mechanism: string,
     *     estimated_volume_m3: float,
     *     status: string,
     *     investigated_at: string
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $incident = MiningGeotechIncident::query()->findOrFail($data['geotech_incident_id']);

        $currentStatus = GeotechIncidentStatus::tryFrom($incident->status) ?? GeotechIncidentStatus::MONITORING_NORMAL;
        $targetStatus = GeotechIncidentStatus::INSPECTED_BY_GEOTECH;

        if ($this->stateMachine->canTransition($currentStatus, $targetStatus)) {
            $incident->status = $targetStatus->value;
        }

        $meta = $incident->metadata ?? [];
        $meta['geotech_investigation'] = [
            'investigator' => $data['investigator_name'],
            'failure_mechanism' => strtoupper(trim($data['failure_mechanism'])),
            'failure_volume_m3_estimate' => (float) $data['failure_volume_m3_estimate'],
            'dip_direction' => (float) $data['dip_direction_degrees'],
            'dip_angle' => (float) $data['dip_angle_degrees'],
            'water_condition' => strtoupper(trim($data['water_condition'])),
            'findings' => $data['findings_summary'],
            'investigated_at' => now()->toIso8601String(),
        ];
        $incident->notes = ($incident->notes ? $incident->notes."\n" : '').
            'Investigation: '.$data['findings_summary'];
        $incident->metadata = $meta;
        $incident->save();

        return [
            'incident_code' => $incident->incident_code,
            'failure_mechanism' => strtoupper(trim($data['failure_mechanism'])),
            'estimated_volume_m3' => (float) $data['failure_volume_m3_estimate'],
            'status' => $incident->status,
            'investigated_at' => now()->toIso8601String(),
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

        if (empty($data['investigator_name'])) {
            throw new InvalidArgumentException('Nama geotech engineer pemeriksa wajib diisi.');
        }

        $validMechanisms = ['CIRCULAR_ROTATIONAL', 'PLANAR_SLIDE', 'WEDGE_FAILURE', 'TOPPLING', 'RAVELING', 'SINKHOLE'];
        if (! in_array(strtoupper($data['failure_mechanism'] ?? ''), $validMechanisms, true)) {
            throw new InvalidArgumentException('Mekanisme kegagalan lereng tidak valid: '.($data['failure_mechanism'] ?? ''));
        }
    }
}
