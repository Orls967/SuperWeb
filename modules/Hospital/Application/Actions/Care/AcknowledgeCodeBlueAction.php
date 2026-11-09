<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\CodeBlueEvent;
use Modules\Hospital\Domain\StateMachines\CodeBlueEventStateMachine;

/**
 * Aksi Konfirmasi Respons Alarm Code Blue oleh Perawat / Dokter (Fase 87.5).
 */
class AcknowledgeCodeBlueAction
{
    public function execute(int $eventId, string $role = 'nurse', ?int $actorId = null): CodeBlueEvent
    {
        $event = CodeBlueEvent::findOrFail($eventId);

        if (strtolower($role) === 'doctor') {
            $event->doctor_ack_at = now();
            CodeBlueEventStateMachine::transition($event, CodeBlueEventStateMachine::STATUS_TEAM_DISPATCHED, 'Dokter mengonfirmasi kedatangan di lokasi', $actorId);
        } else {
            $event->nurse_ack_at = now();
            CodeBlueEventStateMachine::transition($event, CodeBlueEventStateMachine::STATUS_ACKNOWLEDGED, 'Perawat ruangan merespons alarm awal', $actorId);
        }

        return $event->fresh();
    }
}
