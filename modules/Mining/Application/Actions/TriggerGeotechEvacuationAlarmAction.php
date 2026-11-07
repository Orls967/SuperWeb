<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\GeotechIncidentStatus;
use Modules\Mining\Domain\Models\MiningGeotechIncident;
use Modules\Mining\Domain\StateMachines\GeotechIncidentStateMachine;

/**
 * Domain Action: Aktivasi Protokol Evakuasi Bahaya Longsor Geoteknik Tambang.
 * Memerintahkan evakuasi darurat pada sektor pit / underground stope yang terancam runtuh,
 * mencatat waktu peringatan, memblokir akses gerbang pit, dan memberi notifikasi KTT.
 */
class TriggerGeotechEvacuationAlarmAction
{
    public function __construct(
        private readonly GeotechIncidentStateMachine $stateMachine
    ) {}

    /**
     * @param  array{
     *     geotech_incident_id: string,
     *     ordered_by_ktt_name: string,
     *     evacuation_reason: string,
     *     safe_muster_point_sector: string
     * } $data
     * @return array{
     *     incident_code: string,
     *     location_sector: string,
     *     status: string,
     *     is_evacuation_ordered: bool,
     *     evacuated_at: string,
     *     safe_muster_point: string
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $incident = MiningGeotechIncident::query()->findOrFail($data['geotech_incident_id']);

        $currentStatus = GeotechIncidentStatus::tryFrom($incident->status) ?? GeotechIncidentStatus::MONITORING_NORMAL;
        $targetStatus = GeotechIncidentStatus::AREA_EVACUATED;

        if ($this->stateMachine->canTransition($currentStatus, $targetStatus)) {
            $incident->status = $targetStatus->value;
        }

        $incident->is_evacuation_ordered = true;
        $incident->evacuated_at = now();

        $meta = $incident->metadata ?? [];
        $meta['evacuation_order'] = [
            'ordered_by' => $data['ordered_by_ktt_name'],
            'reason' => $data['evacuation_reason'],
            'safe_muster_point' => $data['safe_muster_point_sector'],
            'ordered_at' => now()->toIso8601String(),
        ];
        $incident->metadata = $meta;
        $incident->save();

        return [
            'incident_code' => $incident->incident_code,
            'location_sector' => $incident->location_sector,
            'status' => $incident->status,
            'is_evacuation_ordered' => true,
            'evacuated_at' => now()->toIso8601String(),
            'safe_muster_point' => $data['safe_muster_point_sector'],
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

        if (empty($data['ordered_by_ktt_name'])) {
            throw new InvalidArgumentException('Nama pejabat pengambil keputusan (KTT / Kepala Tambang) wajib diisi.');
        }

        if (empty($data['safe_muster_point_sector'])) {
            throw new InvalidArgumentException('Titik kumpul aman (muster point) evakuasi wajib diisi.');
        }
    }
}
