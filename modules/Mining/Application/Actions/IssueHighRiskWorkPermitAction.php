<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Mining\Domain\Enums\WorkPermitStatus;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Mining\Domain\StateMachines\WorkPermitStateMachine;

class IssueHighRiskWorkPermitAction
{
    /**
     * Terbitkan Izin Kerja Berisiko Tinggi (Permit to Work - PTW) K3 Tambang (D94-5).
     *
     * @param array{
     *     site_id: string,
     *     worker_id: string,
     *     activity_type: string,
     *     geofence_zone: string,
     *     risk_level: string,
     *     valid_hours: int,
     *     supervisor_name: string,
     *     approved_by_user_id?: int
     * } $data
     */
    public function execute(array $data): MiningWorkPermit
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $workerId = trim((string) $data['worker_id']);
            $activityType = strtoupper((string) $data['activity_type']);
            $zone = trim((string) $data['geofence_zone']);
            $risk = strtolower((string) ($data['risk_level'] ?? 'high'));
            $hours = (int) ($data['valid_hours'] ?? 8);

            if ($hours <= 0 || $hours > 24) {
                throw new InvalidArgumentException('Masa berlaku izin kerja harus berada dalam rentang 1 - 24 jam.');
            }

            $site = MiningSite::findOrFail($siteId);

            $now = CarbonImmutable::now();
            $validUntil = $now->addHours($hours);
            $permitNumber = 'PTW-'.strtoupper(substr($activityType, 0, 4)).'-'.date('Ymd').'-'.strtoupper(Str::random(5));

            $permit = MiningWorkPermit::create([
                'id' => (string) Str::uuid(),
                'site_id' => $site->id,
                'permit_number' => $permitNumber,
                'worker_id' => $workerId,
                'permit_type' => $activityType,
                'activity_type' => $activityType,
                'geofence_zone' => $zone,
                'risk_level' => $risk,
                'status' => WorkPermitStatus::REQUESTED,
                'valid_from' => $now,
                'valid_until' => $validUntil,
                'supervisor_name' => $data['supervisor_name'] ?? 'Pengawas K3 Tambang',
                'approved_by_user_id' => $data['approved_by_user_id'] ?? null,
            ]);

            $sm = new WorkPermitStateMachine($permit);
            $sm->transitionTo(WorkPermitStatus::RISK_ASSESSED, 'Penilaian risiko JSEA dan checklist APD tuntas');
            $sm->transitionTo(WorkPermitStatus::APPROVED, 'Persetujuan Pengawas Operasional Tambang (KTT)');
            $sm->transitionTo(WorkPermitStatus::ACTIVE, 'Izin aktif di lapangan tambang');

            return $permit;
        });
    }
}
