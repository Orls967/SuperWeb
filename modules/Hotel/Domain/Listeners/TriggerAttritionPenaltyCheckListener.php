<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Hotel\Domain\Events\MiceMasterFolioClosedEvent;

final class TriggerAttritionPenaltyCheckListener
{
    public function handle(MiceMasterFolioClosedEvent $event): void
    {
        Log::info('Triggering post-event attrition audit for closed MICE folio', [
            'master_folio_id' => $event->masterFolioId,
            'contract_number' => $event->contractNumber,
            'final_amount' => $event->finalSettledAmount,
            'closed_at' => $event->closedAt,
        ]);
    }
}
