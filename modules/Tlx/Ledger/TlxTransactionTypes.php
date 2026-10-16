<?php

declare(strict_types=1);

namespace Modules\Tlx\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum TlxTransactionTypes: string implements TransactionTypeRegistry
{
    case TELECOM_CLOUD_COMPUTE_CHARGEBACK = 'TELECOM_CLOUD_COMPUTE_CHARGEBACK';
    case TELECOM_INTERCONNECT_SETTLEMENT = 'TELECOM_INTERCONNECT_SETTLEMENT';
    case TELECOM_IOT_CONNECTIVITY_BILLING = 'TELECOM_IOT_CONNECTIVITY_BILLING';
}
