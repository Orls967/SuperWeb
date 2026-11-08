<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Hotel\Domain\Events\WeddingMilestonePaidEvent;

final class UpdateWeddingMilestoneStatusListener
{
    public function handle(WeddingMilestonePaidEvent $event): void
    {
        Log::info('Wedding milestone settled and perks unlocked', [
            'schedule_id' => $event->scheduleId,
            'contract_number' => $event->contractNumber,
            'milestone_index' => $event->milestoneIndex,
            'milestone_name' => $event->milestoneName,
            'paid_amount' => $event->amountPaid,
            'remaining' => $event->remainingBalance,
        ]);
    }
}
