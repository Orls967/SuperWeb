<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\CrowdCrushIncident;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\GateTurnstile;

/**
 * Layanan Audit Forensik Keselamatan Kerumunan & Kepatuhan Regulasi Gerbang (Safety & Privacy Auditor).
 */
final class VenueCrowdSafetyAuditService
{
    /**
     * Jalankan audit menyeluruh kepatuhan keselamatan kerumunan dan batas legalitas zona.
     *
     * @return array{
     *   audited_zones_count: int,
     *   overcapacity_violations: int,
     *   locked_gate_anomalies: int,
     *   active_incidents_count: int,
     *   total_headcount_reconciled: int,
     *   safety_health_status: string,
     *   findings: array<int, string>
     * }
     */
    public function auditZoneCapacityCompliance(): array
    {
        $zones = EntertainmentZone::query()->get();
        $violations = 0;
        $gateAnomalies = 0;
        $findings = [];
        $totalHeadcount = 0;

        foreach ($zones as $zone) {
            $cap = (int) $zone->capacity_limit;
            $occ = (int) $zone->current_occupancy;
            $totalHeadcount += $occ;

            // 1. Verifikasi Kelebihan Muatan Fisik
            if ($cap > 0 && $occ > $cap) {
                $violations++;
                $findings[] = sprintf(
                    'PELANGGARAN_KAPASITAS: Zona [%s] memiliki okupansi %d melebihi batas legal %d',
                    $zone->zone_code,
                    $occ,
                    $cap
                );
            }

            // 2. Verifikasi Status Pintu Darurat
            $emergencyGates = GateTurnstile::where('zone_id', $zone->id)->where('is_emergency_exit', true)->get();
            foreach ($emergencyGates as $gate) {
                if ($gate->is_locked && $occ >= ($cap * 0.90)) {
                    $gateAnomalies++;
                    $findings[] = sprintf(
                        'PINTU_DARURAT_TERKUNCI: Gerbang darurat [%s] terkunci padahal zona berkapasitas >= 90%%',
                        $gate->gate_code
                    );
                }
            }
        }

        $activeIncidents = CrowdCrushIncident::whereNull('resolved_at')->count();

        $status = match (true) {
            $violations > 0 || $gateAnomalies > 0 => 'CRITICAL_ACTION_REQUIRED',
            $activeIncidents > 0 => 'ELEVATED_WATCH',
            default => 'SYSTEM_HEALTHY',
        };

        return [
            'audited_zones_count' => $zones->count(),
            'overcapacity_violations' => $violations,
            'locked_gate_anomalies' => $gateAnomalies,
            'active_incidents_count' => $activeIncidents,
            'total_headcount_reconciled' => $totalHeadcount,
            'safety_health_status' => $status,
            'findings' => $findings,
        ];
    }

    /**
     * Audit Konsistensi Aliran Turnstile (Ingress vs Egress Scan Balance).
     *
     * @return array{
     *   total_turnstiles: int,
     *   sum_scans_in: int,
     *   sum_scans_out: int,
     *   net_occupancy_from_scans: int,
     *   variance_against_sensor_headcount: int,
     *   discrepancy_detected: bool
     * }
     */
    public function auditTurnstileFlowReconciliation(int $venueId): array
    {
        $turnstiles = GateTurnstile::where('venue_id', $venueId)->get();
        $sumIn = (int) $turnstiles->sum('total_scans_in');
        $sumOut = (int) $turnstiles->sum('total_scans_out');
        $netFromScans = max(0, $sumIn - $sumOut);

        $actualOccupancy = (int) EntertainmentZone::where('venue_id', $venueId)->sum('current_occupancy');
        $variance = abs($netFromScans - $actualOccupancy);

        return [
            'total_turnstiles' => $turnstiles->count(),
            'sum_scans_in' => $sumIn,
            'sum_scans_out' => $sumOut,
            'net_occupancy_from_scans' => $netFromScans,
            'variance_against_sensor_headcount' => $variance,
            'discrepancy_detected' => $variance > 50,
        ];
    }
}
