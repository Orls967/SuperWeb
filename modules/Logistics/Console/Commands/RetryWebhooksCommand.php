<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Actions\DispatchWebhookAction;

class RetryWebhooksCommand extends Command
{
    protected $signature = 'lgx:retry-webhooks';

    protected $description = 'Retry failed webhook deliveries that are due for retry';

    public function handle(DispatchWebhookAction $action): int
    {
        $retried = $action->retryPending();
        $this->info("Retried {$retried} webhook deliveries.");

        return self::SUCCESS;
    }
}
