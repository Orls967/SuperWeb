<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Services\MineBlastingSeismicMonitor;

class EvaluateBlastingVibrationAction
{
    public function __construct(
        private readonly MineBlastingSeismicMonitor $monitor
    ) {}

    /**
     * Evaluasi tingkat getaran seismik dan airblast peledakan tambang (D94-5).
     *
     * @param array{
     *     site_id: string,
     *     blast_event_id: string,
     *     distance_meters: float,
     *     charge_per_delay_kg: float,
     *     dominant_frequency_hz?: float,
     *     structure_class?: string
     * } $data
     * @return array{
     *     site_id: string,
     *     blast_event_id: string,
     *     scaled_distance: float,
     *     predicted_ppv_mms: float,
     *     predicted_airblast_dbl: float,
     *     regulatory_ppv_limit_mms: float,
     *     regulatory_airblast_limit_dbl: float,
     *     is_seismic_compliant: bool,
     *     is_airblast_compliant: bool,
     *     overall_safety_status: string,
     *     mitigation_directive: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $site = MiningSite::findOrFail($siteId);

            $result = $this->monitor->evaluate([
                'blast_event_id' => (string) $data['blast_event_id'],
                'distance_meters' => (float) $data['distance_meters'],
                'charge_per_delay_kg' => (float) $data['charge_per_delay_kg'],
                'dominant_frequency_hz' => (float) ($data['dominant_frequency_hz'] ?? 30.0),
                'structure_class' => (string) ($data['structure_class'] ?? 'RESIDENTIAL'),
            ]);

            return array_merge(['site_id' => $site->id], $result);
        });
    }
}
