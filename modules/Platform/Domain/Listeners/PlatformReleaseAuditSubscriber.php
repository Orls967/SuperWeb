<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Domain\Events\BackfillChunkCompletedEvent;
use Modules\Platform\Domain\Events\CanarySpikeDetectedEvent;
use Modules\Platform\Domain\Events\ChangeApprovedEvent;
use Modules\Platform\Domain\Events\ChangeRiskAssessedEvent;
use Modules\Platform\Domain\Events\DeploymentInitiatedEvent;
use Modules\Platform\Domain\Events\ReleaseTrainFrozenEvent;
use Modules\Platform\Domain\Events\RollbackExecutedEvent;

final class PlatformReleaseAuditSubscriber
{
    public function onRiskAssessed(ChangeRiskAssessedEvent $event): void
    {
        Log::info("AUDIT: Change {$event->changeRequest->reference_number} dinilai dengan skor {$event->score} (Tier: {$event->tier})");
    }

    public function onChangeApproved(ChangeApprovedEvent $event): void
    {
        Log::info("AUDIT: Change {$event->changeRequest->reference_number} disetujui oleh user #{$event->approverId}");
    }

    public function onDeploymentInitiated(DeploymentInitiatedEvent $event): void
    {
        Log::info("AUDIT: Deployment #{$event->deployment->id} dimulai pada lingkungan {$event->deployment->environment}");
    }

    public function onCanarySpike(CanarySpikeDetectedEvent $event): void
    {
        Log::warning("AUDIT ANOMALI: Lonjakan galat canary terdeteksi pada deployment #{$event->deployment->id}, Z-Score: {$event->observation->z_score}");
    }

    public function onRollbackExecuted(RollbackExecutedEvent $event): void
    {
        Log::alert("AUDIT DARURAT: Rollback dieksekusi untuk deployment #{$event->deployment->id}. Alasan: {$event->reason}");
    }

    public function onTrainFrozen(ReleaseTrainFrozenEvent $event): void
    {
        Log::info("AUDIT: Release train {$event->train->train_code} dibekukan (code freeze).");
    }

    public function onBackfillChunk(BackfillChunkCompletedEvent $event): void
    {
        Log::debug("AUDIT: Chunk backfill #{$event->chunk->chunk_index} selesai ({$event->processedRecords} record).");
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ChangeRiskAssessedEvent::class, [self::class, 'onRiskAssessed']);
        $events->listen(ChangeApprovedEvent::class, [self::class, 'onChangeApproved']);
        $events->listen(DeploymentInitiatedEvent::class, [self::class, 'onDeploymentInitiated']);
        $events->listen(CanarySpikeDetectedEvent::class, [self::class, 'onCanarySpike']);
        $events->listen(RollbackExecutedEvent::class, [self::class, 'onRollbackExecuted']);
        $events->listen(ReleaseTrainFrozenEvent::class, [self::class, 'onTrainFrozen']);
        $events->listen(BackfillChunkCompletedEvent::class, [self::class, 'onBackfillChunk']);
    }
}
