<?php

declare(strict_types=1);

namespace Modules\ProServices\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum ProServicesTransactionTypes: string implements TransactionTypeRegistry
{
    case REVENUE_RECOGNITION = 'PSV_REVENUE_RECOG';
    case INVOICE_ISSUANCE = 'PSV_INVOICE_ISSUE';
    case CLIENT_PAYMENT = 'PSV_CLIENT_PAYMENT';
}
