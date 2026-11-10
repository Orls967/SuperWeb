<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\EventSpine\ReplaySpineStreamAction;

class SpineReplayCommand extends Command
{
    protected $signature = 'spine:replay
        {group : Consumer group to replay}
        {--topic=* : Topic pattern filter}
        {--from=0 : Starting offset}
        {--to= : Ending offset}';

    protected $description = 'Replay event spine stream deterministically without side effects and verify projection hash';

    public function handle(ReplaySpineStreamAction $action): int
    {
        $group = (string) $this->argument('group');
        $topics = (array) $this->option('topic');
        $topic = ! empty($topics) && $topics[0] !== null ? (string) $topics[0] : '*';

        $fromOffset = (int) $this->option('from');
        $toOffset = $this->option('to') !== null ? (int) $this->option('to') : null;

        $this->info("Replaying spine stream for group [{$group}] topic [{$topic}] from offset {$fromOffset}...");

        $res = $action->execute($group, $topic, $fromOffset, $toOffset);

        $this->info("Replay completed. Events replayed: {$res['events_count']}.");
        $this->info("Canonical Projection Hash: {$res['projection_hash']}");

        return self::SUCCESS;
    }
}
