<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\SpineConsumerRegistry;
use Modules\Core\Contracts\EventSpineInterface;

class ConsumeSpineCommand extends Command
{
    protected $signature = 'core:consume-spine
        {group : Consumer group identifier (e.g. accounting, logistics, analytics)}
        {--topic=* : Topic pattern filter (e.g. auto.*, fintech.*, *)}
        {--from-offset= : Explicit starting offset to read or replay from}
        {--limit=100 : Maximum events to fetch per batch}
        {--replay : Allow replaying already committed offsets}
        {--once : Consume a single batch and exit}';

    protected $description = 'Worker to consume events from the event spine by consumer group with persistent offset and idempotent replay';

    public function handle(EventSpineInterface $spine): int
    {
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
                SpineConsumerRegistry::dispatch($group, $event);
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
