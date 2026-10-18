<?php

declare(strict_types=1);

namespace Modules\Rwa\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum RwaTransactionTypes: string implements TransactionTypeRegistry
{
    case RWA_DIVIDEND = 'rwa_dividend';
    case RWA_ISSUANCE = 'rwa_issuance';
    case RWA_SECONDARY_TRADE = 'rwa_secondary_trade';
    case RWA_REDEMPTION = 'rwa_redemption';
}
