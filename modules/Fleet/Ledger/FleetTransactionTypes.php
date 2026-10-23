<?php

declare(strict_types=1);

namespace Modules\Fleet\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum FleetTransactionTypes: string implements TransactionTypeRegistry
{
    case LEASE_AMORTIZATION = 'lease_amortization';
    case SLA_COMPENSATION = 'sla_compensation';
}
