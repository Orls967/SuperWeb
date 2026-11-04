<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum TradeFinanceTransactionTypes: string implements TransactionTypeRegistry
{
    case CROSSBORDER_ESCROW_DEPOSIT = 'CROSSBORDER_ESCROW_DEPOSIT';
    case CROSSBORDER_ESCROW_RELEASE = 'CROSSBORDER_ESCROW_RELEASE';
    case CROSSBORDER_ESCROW_REFUND = 'CROSSBORDER_ESCROW_REFUND';
    case TF_LC_ISSUED = 'TF_LC_ISSUED';
    case CLEARING_USDT_DEPOSIT = 'CLEARING_USDT_DEPOSIT';
    case CLEARING_USDT_RELEASE = 'CLEARING_USDT_RELEASE';
    case CLEARING_FX_REVAL = 'CLEARING_FX_REVAL';
    case CLEARING_USDT_FUNDING = 'CLEARING_USDT_FUNDING';
}
