<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum EnterpriseFinanceTransactionTypes: string implements TransactionTypeRegistry
{
    case HOLDING_DIVIDEND_DECLARATION = 'HOLDING_DIVIDEND_DECLARATION';
}
