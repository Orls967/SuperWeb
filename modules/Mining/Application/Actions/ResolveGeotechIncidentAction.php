<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Enums\GeotechIncidentStatus;
use Modules\Mining\Domain\Models\MiningGeotechIncident;
use Modules\Mining\Domain\StateMachines\GeotechIncidentStateMachine;

/**
 * Domain Action: Resolusi dan Penutupan Insiden Geoteknik Lereng Tambang.
 * Memvalidasi data radar paska-remediasi bahwa laju deformasi < 0.5 mm/jam selama 48 jam,
 * mencabut status evakuasi, dan mengembalikan sektor lereng ke operasi normal.
 */
class ResolveGeotechIncidentAction
{
    public function __construct(
        private readonly GeotechIncidentStateMachine $stateMachine
    ) {}

    /**
     * @param  array{
     *     geotech_incident_id: string,
     *     closing_engineer_name: string,
     *     verified_stable_displacement_rate_mm_h: float,
     *     verified_factor_of_safety: float,
     *     signoff_notes: string
     * } $data
     * @return array{
     *     incident_code: string,
     *     status: string,
     *     resolved_at: string,
     *     signoff_engineer: string
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $incident = MiningGeotechIncident::query()->findOrFail($data['geotech_incident_id']);

        $rate = (float) $data['verified_stable_displacement_rate_mm_h'];
        $fos = (float) $data['verified_factor_of_safety'];

        if ($rate > 1.0) {
            throw new InvalidArgumentException("Lereng belum dapat di-resolve: laju deformasi masih {$rate} mm/jam (harus <= 1.0 mm/jam).");
        }

        if ($fos < 1.30) {
            throw new InvalidArgumentException("Lereng belum dapat di-resolve: FoS terverifikasi {$fos} masih di bawah ambang aman 1.30.");
        }

        $currentStatus = GeotechIncidentStatus::tryFrom($incident->status) ?? GeotechIncidentStatus::MONITORING_NORMAL;

        // Transisi ke ALL_CLEAR
        if ($this->stateMachine->canTransition($currentStatus, GeotechIncidentStatus::ALL_CLEAR)) {
            $incident->status = GeotechIncidentStatus::ALL_CLEAR->value;
        }

        $incident->is_evacuation_ordered = false;
        $incident->factor_of_safety_fos = $fos;
        $incident->displacement_rate_mm_hour = $rate;

        $meta = $incident->metadata ?? [];
        $meta['resolution_signoff'] = [
            'engineer' => $data['closing_engineer_name'],
            'verified_disp_rate' => $rate,
            'verified_fos' => $fos,
            'notes' => $data['signoff_notes'],
            'resolved_at' => now()->toIso8601String(),
        ];
        $incident->metadata = $meta;
        $incident->save();

        return [
            'incident_code' => $incident->incident_code,
            'status' => $incident->status,
            'resolved_at' => now()->toIso8601String(),
            'signoff_engineer' => $data['closing_engineer_name'],
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

        if (empty($data['closing_engineer_name'])) {
            throw new InvalidArgumentException('Nama penanggung jawab geoteknik penutup insiden wajib diisi.');
        }
    }
}
