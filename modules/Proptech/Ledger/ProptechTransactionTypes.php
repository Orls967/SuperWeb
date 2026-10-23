<?php

declare(strict_types=1);

namespace Modules\Proptech\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum ProptechTransactionTypes: string implements TransactionTypeRegistry
{
    case BIM_CAPITALIZE_CIP = 'BIM_CAPITALIZE_CIP';
    case FLEX_CAPTURE_REVENUE = 'FLEX_CAPTURE_REVENUE';
    case FLEX_DEPOSIT_HOLD = 'FLEX_DEPOSIT_HOLD';
    case FLEX_NO_SHOW_FEE = 'FLEX_NO_SHOW_FEE';
    case PROPTECH_UTILITY_BILLING = 'PROPTECH_UTILITY_BILLING';
}
