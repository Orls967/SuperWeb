<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\EventSpine\ClaimConsumerInboxAction;
use Modules\Core\Application\Services\PoisonMessageQuarantineService;
use Modules\Core\Application\Services\SpineConsumerRegistry;
use Modules\Core\Contracts\EventSpineInterface;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\StateMachines\EventDeliveryStateMachine;
use Throwable;

class ConsumeSpineCommand extends Command
{
    protected $signature = 'core:consume-spine
        {group : Consumer group identifier (e.g. accounting, logistics, analytics)}
        {--topic=* : Topic pattern filter (e.g. auto.*, fintech.*, *)}
        {--from-offset= : Explicit starting offset to read or replay from}
        {--limit=100 : Maximum events to fetch per batch}
        {--replay : Allow replaying already committed offsets}
        {--once : Consume a single batch and exit}';

    protected $description = 'Worker to consume events from the event spine by consumer group with persistent offset, persistent inbox idempotency and DLQ backoff';

    public function handle(
        EventSpineInterface $spine,
        ClaimConsumerInboxAction $inbox,
        EventDeliveryStateMachine $stateMachine,
        PoisonMessageQuarantineService $quarantineService
    ): int {
        $group = (string) $this->argument('group');
        $topics = (array) $this->option('topic');
        if (empty($topics) || (count($topics) === 1 && $topics[0] === null)) {
            $topics = ['*'];
        }

        $limit = max(1, (int) $this->option('limit'));
        $fromOffsetOption = $this->option('from-offset');
        $isReplay = (bool) $this->option('replay');

        $totalProcessed = 0;

        foreach ($topics as $topic) {
            $topic = (string) $topic;

            if ($fromOffsetOption !== null) {
                $startOffset = max(0, (int) $fromOffsetOption);
            } else {
                $startOffset = $spine->getOffset($group, $topic);
            }

            $this->info("Consuming spine for group [{$group}] topic [{$topic}] starting from offset {$startOffset}...");

            $events = $spine->consume($topic, $startOffset, $limit);
            if ($events->isEmpty()) {
                $this->info("No new events found for topic [{$topic}].");

                continue;
            }

            $maxOffset = $startOffset;
            $batchCount = 0;

            foreach ($events as $event) {
                $eventId = (string) $event->event_id;

                // Periksa atau buat record delivery
                $delivery = CoreEventDelivery::firstOrCreate(
                    [
                        'message_id' => $eventId,
                        'consumer_group' => $group,
                    ],
                    [
                        'topic' => $event->topic,
                        'status' => EventDeliveryStateMachine::STATUS_PENDING,
                        'attempts' => 0,
                    ]
                );

                // Jika bukan replay dan sudah delivered di inbox, lewati
                if (! $isReplay && $inbox->hasProcessed($group, $eventId)) {
                    if ($event->id > $maxOffset) {
                        $maxOffset = (int) $event->id;
                    }
                    $batchCount++;

                    continue;
                }

                // Cek karantina poison: lewati tanpa memblokir stream
                if ($quarantineService->isQuarantined($eventId, $group)) {
                    if ($event->id > $maxOffset) {
                        $maxOffset = (int) $event->id;
                    }
                    $batchCount++;

                    continue;
                }

                // Jalankan transisi dispatched
                if ($stateMachine->canTransition($delivery->status, EventDeliveryStateMachine::STATUS_DISPATCHED)) {
                    $stateMachine->transition($delivery, EventDeliveryStateMachine::STATUS_DISPATCHED);
                }

                $startTime = microtime(true);
                try {
                    // Claim inbox secara atomik
                    $claimed = $inbox->claim($group, $eventId, 'SpineConsumerRegistry');

                    // Dispatch ke registry
                    SpineConsumerRegistry::dispatch($group, $event);

                    $durationMs = (int) round((microtime(true) - $startTime) * 1000);
                    $inbox->recordSuccess($group, $eventId, null, $durationMs);

                    if ($stateMachine->canTransition($delivery->status, EventDeliveryStateMachine::STATUS_DELIVERED)) {
                        $stateMachine->transition($delivery, EventDeliveryStateMachine::STATUS_DELIVERED);
                    }
                } catch (Throwable $e) {
                    $attempts = $delivery->attempts + 1;

                    // Karantina jika gagal 3 kali berturut-turut
                    if ($attempts >= 3) {
                        $quarantineService->quarantine(
                            messageId: $eventId,
                            consumerGroup: $group,
                            topic: $event->topic,
                            eventName: $event->event_name,
                            payload: (array) $event->payload,
                            failureReason: $e->getMessage(),
                            exception: $e,
                            attempts: $attempts
                        );
                    }

                    if ($attempts >= EventDeliveryStateMachine::MAX_ATTEMPTS) {
                        if ($stateMachine->canTransition($delivery->status, EventDeliveryStateMachine::STATUS_DEAD_LETTERED)) {
                            $stateMachine->transition($delivery, EventDeliveryStateMachine::STATUS_DEAD_LETTERED, $e->getMessage());
                        }
                    } else {
                        if ($stateMachine->canTransition($delivery->status, EventDeliveryStateMachine::STATUS_RETRYING)) {
                            $stateMachine->transition($delivery, EventDeliveryStateMachine::STATUS_RETRYING, $e->getMessage());
                        }
                    }
                }

                if ($event->id > $maxOffset) {
                    $maxOffset = (int) $event->id;
                }
                $batchCount++;
            }

            $totalProcessed += $batchCount;

            // Commit offset if advancing or replay allowed
            if ($maxOffset > $startOffset || ! $isReplay) {
                $spine->commitOffset($group, $topic, $maxOffset);
                $this->info("Committed offset {$maxOffset} for group [{$group}] on topic [{$topic}] (processed {$batchCount} events).");
            } else {
                $this->info("Replayed {$batchCount} events without committing past offset {$startOffset}.");
            }
        }

        $this->info("Finished consuming. Total events processed: {$totalProcessed}.");

        return self::SUCCESS;
    }
}
