<?php

declare(strict_types=1);

namespace Modules\Wealth\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum WealthTransactionTypes: string implements TransactionTypeRegistry
{
    case WM_AUTO_INVEST = 'wm_auto_invest';
}
