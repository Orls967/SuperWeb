<?php

declare(strict_types=1);

namespace Modules\Port\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum PortTransactionTypes: string implements TransactionTypeRegistry
{
    case PORT_TERMINAL_BILLING = 'PORT_TERMINAL_BILLING';
}
