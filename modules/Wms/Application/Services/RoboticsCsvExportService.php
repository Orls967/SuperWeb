<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Wms\Models\Robot;
use Modules\Wms\Models\RoboticsRoiProposal;
use Modules\Wms\Models\RobotMission;
use Modules\Wms\Models\RobotSafetyIncident;
use Modules\Wms\Models\RobotWave;
use Modules\Wms\Models\ZoneReservation;

/**
 * Ekspor CSV deterministik untuk seluruh entitas dashboard robotika Fase 279 (T-6b).
 */
final class RoboticsCsvExportService
{
    public function exportEntity(string $entity): string
    {
        $rows = match ($entity) {
            'robots' => Robot::query()->orderBy('robot_code')->get()->map(static fn (Robot $r): array => [
                $r->robot_code,
                $r->robot_type,
                $r->capacity_units,
                $r->battery_pct,
                $r->is_online ? 'online' : 'offline',
                $r->health_score,
                $r->device_mode,
            ])->all(),
            'missions' => RobotMission::query()->orderBy('id')->get()->map(static fn (RobotMission $m): array => [
                $m->mission_code,
                $m->zone_code,
                $m->status,
                $m->priority,
                $m->retry_count,
                $m->fallback_manual ? 'yes' : 'no',
                $m->failure_reason ?? '',
            ])->all(),
            'reservations' => ZoneReservation::query()->orderBy('id')->get()->map(static fn (ZoneReservation $r): array => [
                $r->id,
                $r->traffic_zone_id,
                $r->mission_id ?? '',
                $r->start_min,
                $r->end_min,
                $r->status,
            ])->all(),
            'waves' => RobotWave::query()->orderBy('id')->get()->map(static fn (RobotWave $w): array => [
                $w->wave_code,
                $w->carrier_code,
                $w->cutoff_min,
                $w->allocated_load,
                $w->capacity,
                $w->status,
            ])->all(),
            'roi' => RoboticsRoiProposal::query()->orderBy('id')->get()->map(static fn (RoboticsRoiProposal $p): array => [
                $p->proposal_code,
                $p->title,
                $p->investment_minor,
                $p->npv_minor,
                $p->payback_months ?? 'NO_PAYBACK',
                $p->recommendation,
                $p->status,
                $p->parameter_label,
            ])->all(),
            'incidents' => RobotSafetyIncident::query()->orderBy('id')->get()->map(static fn (RobotSafetyIncident $i): array => [
                $i->incident_code,
                $i->severity,
                $i->incident_type,
                $i->status,
                $i->simulation_label,
                $i->description,
            ])->all(),
            default => [],
        };

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
