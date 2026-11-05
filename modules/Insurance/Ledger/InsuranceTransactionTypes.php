<?php

declare(strict_types=1);

namespace Modules\Insurance\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum InsuranceTransactionTypes: string implements TransactionTypeRegistry
{
    case INSURANCE_CLAIM_PAYOUT = 'insurance_claim_payout';
    case INSURANCE_PREMIUM = 'insurance_premium';
    case INSURANCE_TREATY_CEDED = 'insurance_treaty_ceded';
    case INSURANCE_CLAIM_RECOVERY = 'insurance_claim_recovery';
}
