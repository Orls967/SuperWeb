<?php

declare(strict_types=1);

namespace Modules\Circular\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum CircularTransactionTypes: string implements TransactionTypeRegistry
{
    case CIRCULAR_ESCROW_HOLD = 'CIRCULAR_ESCROW_HOLD';
    case CIRCULAR_SHRINKAGE_REFUND = 'CIRCULAR_SHRINKAGE_REFUND';
    case CIRCULAR_SELLER_SETTLEMENT = 'CIRCULAR_SELLER_SETTLEMENT';
    case CIRCULAR_SELLER_PAYOUT = 'CIRCULAR_SELLER_PAYOUT';
}
