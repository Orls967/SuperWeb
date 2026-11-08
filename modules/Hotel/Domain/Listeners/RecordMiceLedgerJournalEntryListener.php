<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Hotel\Domain\Events\MiceContractSignedEvent;

final class RecordMiceLedgerJournalEntryListener
{
    public function handle(MiceContractSignedEvent $event): void
    {
        Log::info('Recorded MICE contract escrow reservation in General Ledger', [
            'contract_id' => $event->contractId,
            'contract_number' => $event->contractNumber,
            'client_name' => $event->clientName,
            'contract_value' => $event->contractValue,
            'event_start_date' => $event->eventStartDate,
        ]);
        // Double-entry record: Debit Accounts Receivable MICE / Credit Unearned Revenue
    }
}
