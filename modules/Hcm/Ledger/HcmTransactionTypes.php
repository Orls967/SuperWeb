<?php

declare(strict_types=1);

namespace Modules\Hcm\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum HcmTransactionTypes: string implements TransactionTypeRegistry
{
    case HCM_GIG_BOUNTY_PAYOUT = 'HCM_GIG_BOUNTY_PAYOUT';
    case HCM_PAYROLL_POSTING = 'HCM_PAYROLL_POSTING';
    case HCM_LABOR_ALLOCATION = 'HCM_LABOR_ALLOCATION';
}
