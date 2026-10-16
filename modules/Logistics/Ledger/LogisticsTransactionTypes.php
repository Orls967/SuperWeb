<?php

declare(strict_types=1);

namespace Modules\Logistics\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum LogisticsTransactionTypes: string implements TransactionTypeRegistry
{
    case CIRCULAR_ECONOMY_INTAKE = 'CIRCULAR_ECONOMY_INTAKE';
    case COLD_CHAIN_HOLD_RELEASED = 'COLD_CHAIN_HOLD_RELEASED';
    case COLD_CHAIN_TEMP_BREACH_HOLD = 'COLD_CHAIN_TEMP_BREACH_HOLD';
}
