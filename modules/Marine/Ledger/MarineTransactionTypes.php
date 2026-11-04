<?php

declare(strict_types=1);

namespace Modules\Marine\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum MarineTransactionTypes: string implements TransactionTypeRegistry
{
    case MARINE_ADVANCE_DISBURSEMENT = 'MAR_ADVANCE_DISBURSE';
    case MARINE_FEED_LOAN = 'MAR_FEED_LOAN';
    case MARINE_HARVEST_RECEPTION = 'MAR_HARVEST_RECEPTION';
    case MARINE_PAYOUT_SETTLEMENT = 'MAR_PAYOUT_SETTLE';
}
