<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\CodeBlueEvent;
use Modules\Hospital\Domain\StateMachines\CodeBlueEventStateMachine;

/**
 * Aksi Penyelesaian Darurat Medis Code Blue oleh Tim Resusitasi (Fase 87.5).
 */
class ResolveCodeBlueAction
{
    public function execute(int $eventId, string $resolutionNotes, ?int $actorId = null): CodeBlueEvent
    {
        $event = CodeBlueEvent::findOrFail($eventId);
        CodeBlueEventStateMachine::transition(
            $event,
            CodeBlueEventStateMachine::STATUS_RESOLVED,
            "Pasien stabil / teratasi: {$resolutionNotes}",
            $actorId
        );

        return $event->fresh();
    }
}
