<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\OutboxBusInterface;

class ProcessOutboxCommand extends Command
{
    protected $signature = 'core:process-outbox {--limit=50 : Maximum number of messages to process} {--retry : Also retry failed messages that are due}';

    protected $description = 'Process pending transactional outbox events and dispatch to subscribers';

    public function handle(OutboxBusInterface $bus): int
    {
        $limit = (int) $this->option('limit');
        $dispatched = $bus->dispatchPending($limit);
        $this->info("Dispatched {$dispatched} outbox event(s).");

        if ($this->option('retry')) {
            $retried = $bus->retryDue($limit);
            $this->info("Retried {$retried} due outbox event(s).");
        }

        return self::SUCCESS;
    }
}
