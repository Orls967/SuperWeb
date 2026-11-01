<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\SafetyIncident;
use Modules\Shared\Application\BaseAction;

class EnforceSafetyAreaStopAction extends BaseAction
{
    /**
     * @return object{incident: SafetyIncident, area: MiningArea}
     */
    public function handle(
        string $areaId,
        string $incidentSeverity,
        string $incidentDescription,
        int $reportedByUserId,
        string $idempotencyKey = ''
    ): object {
        return DB::transaction(function () use (
            $areaId,
            $incidentSeverity,
            $incidentDescription,
            $reportedByUserId
        ) {
            $area = MiningArea::lockForUpdate()->findOrFail($areaId);
            $now = Carbon::now();

            $isStopping = in_array(strtoupper($incidentSeverity), ['HIGH', 'CRITICAL', 'FATALITY'], true);
            $incidentCode = sprintf('INC-%s-%s', date('ymd'), strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));

            $incident = SafetyIncident::create([
                'id' => (string) Str::uuid(),
                'incident_code' => $incidentCode,
                'site_id' => $area->site_id,
                'area_id' => $area->id,
                'incident_type' => 'SAFETY_INTERLOCK',
                'severity' => strtoupper($incidentSeverity),
                'area_stopped' => $isStopping,
                'leading_indicator_points' => 0,
                'reporter_party_id' => "USER-{$reportedByUserId}",
                'is_anonymous' => false,
                'description' => $incidentDescription,
                'occurred_at' => $now,
                'reported_at' => $now,
            ]);

            if ($isStopping) {
                $area->status = 'STOPPED';
                $area->stop_reason = $incidentDescription;
                $area->stopped_at = $now;
                $area->save();
            }

            return (object) [
                'incident' => $incident,
                'area' => $area,
            ];
        });
    }
}
