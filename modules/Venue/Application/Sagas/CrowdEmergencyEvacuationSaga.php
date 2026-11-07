<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Sagas;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Contracts\MedicalEmergencyResponseDispatcher;
use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\GateTurnstile;
use Modules\Venue\Domain\StateMachines\CrowdIncidentStateMachine;

/**
 * Saga Terkoordinasi Evakuasi Darurat & Pengendalian Kerumunan Kritis (Fase 117).
 * Mengorkestrasi:
 * 1. Penguncian turnstile masuk (Ingress Lockdown)
 * 2. Pelepasan magnetik gerbang keluar sekunder (Secondary Egress Release)
 * 3. Pemanggilan paramedis cepat via kontrak lintas modul G7-K23
 * 4. Kompensasi otomatis pemulihan status gerbang bila eskalasi dibatalkan.
 */
final class CrowdEmergencyEvacuationSaga
{
    public function __construct(
        private readonly ?MedicalEmergencyResponseDispatcher $medicalDispatcher = null
    ) {}

    /**
     * Eksekusi alur saga evakuasi darurat.
     *
     * @return array{
     *   saga_execution_code: string,
     *   incident_id: int,
     *   locked_turnstiles_count: int,
     *   opened_emergency_exits_count: int,
     *   medical_dispatch_code: ?string,
     *   status: string
     * }
     */
    public function initiateEvacuation(
        int $eventId,
        int $zoneId,
        int $operatorUserId,
        string $emergencyReason
    ): array {
        return DB::transaction(function () use ($eventId, $zoneId, $operatorUserId, $emergencyReason) {
            $zone = EntertainmentZone::where('id', $zoneId)->lockForUpdate()->firstOrFail();
            $event = EntertainmentEvent::where('id', $eventId)->firstOrFail();

            // 1. Kunci Turnstile Masuk
            $lockedCount = GateTurnstile::where('zone_id', $zoneId)
                ->where('is_emergency_exit', false)
                ->update(['is_locked' => true]);

            // 2. Buka Gerbang Keluar Sekunder & Darurat (Fail-Open)
            $openedCount = GateTurnstile::where('zone_id', $zoneId)
                ->where('is_emergency_exit', true)
                ->update(['is_locked' => false]);

            // 3. Catat Insiden Kerumunan Kritis
            $incidentCode = sprintf(
                'INC-SAGA-%d-%d-%s',
                $eventId,
                $zoneId,
                substr(hash_hmac('sha256', "{$eventId}:{$zoneId}", 'SAGA_SALT'), 0, 8)
            );

            $incident = CrowdCrushIncident::create([
                'incident_code' => $incidentCode,
                'zone_id' => $zoneId,
                'event_id' => $eventId,
                'risk_level' => 'CRITICAL',
                'headcount_at_incident' => (int) $zone->current_occupancy,
                'evacuation_corridor' => "KORIDOR-EVAKUASI-SEKUNDER-{$zone->zone_code}",
                'lockdown_initiated_at' => Carbon::now(),
                'ambulance_dispatched' => true,
                'status' => CrowdIncidentStateMachine::STATUS_EVACUATION_STANDBY,
                'reported_by_user_id' => $operatorUserId,
            ]);

            $zone->crowd_lockdown_active = true;
            $zone->save();

            // 4. Panggil Tim Medis via Kontrak Aditif G7-K23
            $medicalCode = null;
            if ($this->medicalDispatcher !== null) {
                $medResult = $this->medicalDispatcher->dispatchMedicalTeam(
                    $zone->venue_id,
                    $zoneId,
                    'CRITICAL',
                    $emergencyReason
                );
                $medicalCode = $medResult['dispatch_code'] ?? null;
            }

            $sagaCode = sprintf('SAGA-EVAC-%d-%s', $incident->id, substr(md5($incidentCode), 0, 8));

            return [
                'saga_execution_code' => $sagaCode,
                'incident_id' => $incident->id,
                'locked_turnstiles_count' => $lockedCount,
                'opened_emergency_exits_count' => $openedCount,
                'medical_dispatch_code' => $medicalCode,
                'status' => 'EVACUATION_COORDINATED',
            ];
        });
    }

    /**
     * Transaksi Kompensasi: Memulihkan gerbang dan menormalkan zona saat insiden teratasi.
     */
    public function compensateAndRestore(int $incidentId, int $operatorUserId, string $clearanceNotes): bool
    {
        return DB::transaction(function () use ($incidentId) {
            $incident = CrowdCrushIncident::where('id', $incidentId)->lockForUpdate()->firstOrFail();
            $zone = $incident->zone()->lockForUpdate()->firstOrFail();

            // Buka kembali gerbang masuk standar
            GateTurnstile::where('zone_id', $zone->id)
                ->where('is_emergency_exit', false)
                ->update(['is_locked' => false]);

            $zone->crowd_lockdown_active = false;
            $zone->save();

            $incident->status = CrowdIncidentStateMachine::STATUS_RESOLVED_CLEAR;
            $incident->resolved_at = Carbon::now();
            $incident->save();

            return true;
        });
    }
}
