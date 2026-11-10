<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Actions\EventSpine\ReplayDeadLetterBatchAction;
use Modules\Core\Domain\Models\CoreEventDelivery;
use Modules\Core\Domain\StateMachines\EventDeliveryStateMachine;

class SpineDlqReplayCommand extends Command
{
    protected $signature = 'spine:dlq-replay
        {--id=* : Specific delivery IDs to replay}
        {--group= : Replay all DLQ messages for a consumer group}
        {--reason=Manual operational retry : Reason for replaying DLQ messages}';

    protected $description = 'Replay dead-lettered messages in batches with operational audit reason';

    public function handle(ReplayDeadLetterBatchAction $action): int
    {
        $ids = (array) $this->option('id');
        $group = $this->option('group');
        $reason = (string) $this->option('reason');

        if (empty($ids) && empty($group)) {
            $this->error('Either --id or --group must be provided to replay DLQ messages.');

            return self::FAILURE;
        }

        if (empty($ids) && $group) {
            $ids = CoreEventDelivery::where('consumer_group', $group)
                ->where('status', EventDeliveryStateMachine::STATUS_DEAD_LETTERED)
                ->pluck('id')
                ->toArray();
        }

        $deliveryIds = array_map('intval', array_filter($ids));
        $this->info('Replaying '.count($deliveryIds).' dead-lettered message(s)...');

        $result = $action->execute($deliveryIds, $reason);

        $this->info("Batch replay completed. Succeeded: {$result['success_count']}, Failed: {$result['failed_count']}.");

        return self::SUCCESS;
    }
}
