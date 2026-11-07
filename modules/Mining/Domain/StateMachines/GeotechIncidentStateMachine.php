<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\StateMachines;

use Carbon\CarbonImmutable;
use Modules\Mining\Domain\Enums\GeotechIncidentStatus;
use Modules\Mining\Domain\Exceptions\InvalidMiningStateTransitionException;
use Modules\Mining\Domain\Models\MiningGeotechIncident;

/**
 * State Machine Penanganan Insiden Kestabilan Lereng Geoteknik (Fase 119.3).
 * Mengawal 8 status eskalasi kegagalan lereng pit dari MONITORING_NORMAL hingga ALL_CLEAR.
 */
class GeotechIncidentStateMachine
{
    protected ?MiningGeotechIncident $incident = null;

    public function __construct(?MiningGeotechIncident $incident = null)
    {
        $this->incident = $incident;
    }

    public function canTransition(GeotechIncidentStatus $current, GeotechIncidentStatus $target): bool
    {
        return $current->canTransitionTo($target);
    }

    public function transitionTo(
        GeotechIncidentStatus $targetStatus,
        ?string $engineer = null,
        ?string $actionTaken = null
    ): void {
        if (! $this->incident) {
            throw new InvalidMiningStateTransitionException('Instance MiningGeotechIncident belum dikonfigurasi dalam state machine.');
        }

        $this->transition($this->incident, $targetStatus, $engineer, $actionTaken);
    }

    public function transition(
        MiningGeotechIncident $incident,
        GeotechIncidentStatus $targetStatus,
        ?string $engineer = null,
        ?string $actionTaken = null
    ): void {
        $currentStatus = $incident->statusEnum();

        if ($currentStatus === $targetStatus) {
            return;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw InvalidMiningStateTransitionException::forEntity(
                'MiningGeotechIncident',
                $currentStatus->value,
                $targetStatus->value,
                "Insiden geoteknik {$incident->incident_code} ({$currentStatus->value}) tidak dapat dialihkan ke {$targetStatus->value}."
            );
        }

        $this->assertTransitionGuard($incident, $currentStatus, $targetStatus);

        $now = CarbonImmutable::now();
        $updates = [
            'status' => $targetStatus->value,
        ];

        if ($engineer !== null) {
            $updates['geotech_engineer'] = $engineer;
        }

        if ($actionTaken !== null) {
            $updates['action_taken'] = $actionTaken;
        }

        if ($targetStatus === GeotechIncidentStatus::ALARM_TRIGGERED) {
            $updates['alert_level'] = 'RED_SHUTDOWN';
        } elseif ($targetStatus === GeotechIncidentStatus::AREA_EVACUATED) {
            $updates['evacuated_at'] = $now;
        } elseif ($targetStatus === GeotechIncidentStatus::INSPECTED_BY_GEOTECH) {
            $updates['inspected_at'] = $now;
        } elseif ($targetStatus === GeotechIncidentStatus::REMEDIATED) {
            $updates['remediated_at'] = $now;
        } elseif ($targetStatus === GeotechIncidentStatus::ALL_CLEAR) {
            $updates['resolved_at'] = $now;
            $updates['alert_level'] = 'GREEN';
        }

        $incident->update($updates);
    }

    private function assertTransitionGuard(
        MiningGeotechIncident $incident,
        GeotechIncidentStatus $current,
        GeotechIncidentStatus $target
    ): void {
        if ($target === GeotechIncidentStatus::ALL_CLEAR) {
            if ((float) $incident->factor_of_safety < 1.30) {
                throw InvalidMiningStateTransitionException::forEntity(
                    'MiningGeotechIncident',
                    $current->value,
                    $target->value,
                    "Pencabutan status darurat ditolak: Faktor Keamanan Lereng (FoS {$incident->factor_of_safety}) masih di bawah batas aman minimum (FoS >= 1.30)."
                );
            }

            if ((float) $incident->velocity_mm_day >= 2.0) {
                throw InvalidMiningStateTransitionException::forEntity(
                    'MiningGeotechIncident',
                    $current->value,
                    $target->value,
                    "Pencabutan status darurat ditolak: Kecepatan pergerakan dinding ({$incident->velocity_mm_day} mm/hari) belum melandai di bawah ambang normal (< 2.0 mm/hari)."
                );
            }
        }
    }
}
