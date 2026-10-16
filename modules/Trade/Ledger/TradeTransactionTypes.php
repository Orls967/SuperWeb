<?php

declare(strict_types=1);

namespace Modules\Trade\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum TradeTransactionTypes: string implements TransactionTypeRegistry
{
    case TENDER_CONTRACT_AWARD = 'TENDER_CONTRACT_AWARD';
    case TRADE_EXPORT_REVENUE = 'TRADE_EXPORT_REVENUE';
}
