<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Services\MineTailingsStorageFacilityMonitor;

class AuditTailingsDamSafetyAction
{
    public function __construct(
        private readonly MineTailingsStorageFacilityMonitor $monitor = new MineTailingsStorageFacilityMonitor
    ) {}

    /**
     * Audit rutin keamanan bendungan penampungan tailing (TSF) konsesi tambang (D94-6).
     *
     * @param array{
     *     site_id: string,
     *     dam_code: string,
     *     crest_elevation_meters: float,
     *     pond_water_elevation_meters: float,
     *     phreatic_line_ratio: float,
     *     toe_seepage_flow_liters_per_minute: float,
     *     historic_baseline_seepage_lpm: float,
     *     cyclic_shear_stress_ratio: float,
     *     cyclic_resistance_ratio: float
     * } $data
     * @return array{
     *     site_id: string,
     *     dam_code: string,
     *     freeboard_meters: float,
     *     safety_verdict: string,
     *     is_inspection_passed: bool,
     *     directive: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $site = MiningSite::findOrFail($siteId);

            $result = $this->monitor->monitor($data);

            $isPassed = ($result['overall_dam_safety_level'] === 'GREEN_STABLE');

            return [
                'site_id' => $siteId,
                'dam_code' => $result['dam_code'],
                'freeboard_meters' => $result['freeboard_meters'],
                'safety_verdict' => $result['overall_dam_safety_level'],
                'is_inspection_passed' => $isPassed,
                'directive' => $result['mitigation_directive'],
            ];
        });
    }
}
