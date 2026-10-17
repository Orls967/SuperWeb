<?php

declare(strict_types=1);

namespace Modules\Esg\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum EsgTransactionTypes: string implements TransactionTypeRegistry
{
    case ESG_CARBON_PURCHASE = 'ESG_CARBON_PURCHASE';
    case ESG_CARBON_RETIREMENT = 'ESG_CARBON_RETIREMENT';
}
