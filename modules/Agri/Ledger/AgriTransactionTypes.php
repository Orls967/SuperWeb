<?php

declare(strict_types=1);

namespace Modules\Agri\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum AgriTransactionTypes: string implements TransactionTypeRegistry
{
    case AGRI_LOAN_DISBURSEMENT = 'AGRI_LOAN_DISBURSEMENT';
    case AGRI_HARVEST_PAYOUT = 'AGRI_HARVEST_PAYOUT';
}
