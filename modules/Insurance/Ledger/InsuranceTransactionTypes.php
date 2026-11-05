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
    case INSURANCE_CEDE_PREMIUM = 'ins_cede_premium';
    case INSURANCE_CEDE_CLAIM = 'ins_cede_claim';
    case INSURANCE_CEDE_TREATY_PREMIUM = 'ins_cede_treaty_premium';
    case INSURANCE_REIN_SETTLEMENT = 'ins_rein_settlement';
    case INSURANCE_REIN_COMMUTATION = 'ins_rein_commutation';
    case INSURANCE_CAPITAL_INJECTION = 'ins_capital_injection';
    case INSURANCE_CATBOND_COUPON = 'ins_catbond_coupon';
    case INSURANCE_CATBOND_RECOVERY = 'ins_catbond_recovery';
}
