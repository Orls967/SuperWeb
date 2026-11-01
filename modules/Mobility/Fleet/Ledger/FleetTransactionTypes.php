<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum FleetTransactionTypes: string implements TransactionTypeRegistry
{
    case LEASE_AMORTIZATION = 'lease_amortization';
    case SLA_COMPENSATION = 'sla_compensation';
    case FLEET_BILL = 'oto:fleet_bill';
    case FLEET_SLA_CREDIT = 'oto:fleet_sla_credit';
}
