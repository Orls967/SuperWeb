<?php

declare(strict_types=1);

namespace Modules\Pricing\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum PricingTransactionTypes: string implements TransactionTypeRegistry
{
    case PRICING_TICK_CAPTURE = 'PRICING_TICK_CAPTURE';
}
