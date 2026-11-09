<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Hospital\Application\Events\CodeBlueTriggeredEvent;
use Modules\Hospital\Application\Events\VitalSignDeterioratedEvent;

final class NotifyRapidResponseTeamListener
{
    public function handleCodeBlue(CodeBlueTriggeredEvent $event): void
    {
        $codeBlue = $event->codeBlueEvent;
        Log::critical("RRT_NOTIFICATION: Code Blue #{$codeBlue->id} diaktifkan di {$codeBlue->location_zone} untuk Admission #{$codeBlue->admission_id}");
    }

    public function handleVitalsDeterioration(VitalSignDeterioratedEvent $event): void
    {
        Log::warning("RRT_NOTIFICATION: Deteriorasi vital sign pasien #{$event->telemetry->patientId} dengan skor NEWS2: {$event->news2Score}");
    }
}
