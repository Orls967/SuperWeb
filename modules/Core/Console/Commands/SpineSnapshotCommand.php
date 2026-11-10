<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\EventSpine\CreateProjectionSnapshotAction;

class SpineSnapshotCommand extends Command
{
    protected $signature = 'spine:snapshot
        {group : Consumer group identifier}
        {--offset=100 : Target offset up to which state is projected and snapshotted}';

    protected $description = 'Create a deterministic projection snapshot for a consumer group and verify canonical hash';

    public function handle(CreateProjectionSnapshotAction $action): int
    {
        $group = (string) $this->argument('group');
        $offset = (int) $this->option('offset');

        $this->info("Creating snapshot for group [{$group}] up to offset {$offset}...");
        $result = $action->execute($group, $offset);

        $this->info("Snapshot created successfully. Offset: {$result['offset']}.");
        $this->info("Canonical State Hash: {$result['hash']}");

        return self::SUCCESS;
    }
}
