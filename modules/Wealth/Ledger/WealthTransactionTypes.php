<?php

declare(strict_types=1);

namespace Modules\Wealth\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum WealthTransactionTypes: string implements TransactionTypeRegistry
{
    case WM_AUTO_INVEST = 'wm_auto_invest';
    case WM_REBALANCE = 'wm_rebalance';
    case WM_FEE_ACCRUAL = 'wm_fee_accrual';
    case WM_WITHDRAWAL = 'wm_withdrawal';
}
