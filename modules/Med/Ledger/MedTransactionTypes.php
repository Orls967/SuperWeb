<?php

declare(strict_types=1);

namespace Modules\Med\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum MedTransactionTypes: string implements TransactionTypeRegistry
{
    case MEDIA_AD_CAMPAIGN_SETTLEMENT = 'MEDIA_AD_CAMPAIGN_SETTLEMENT';
    case MEDIA_TALENT_ROYALTY_ACCRUAL = 'MEDIA_TALENT_ROYALTY_ACCRUAL';
    case MEDIA_STUDIO_BUDGET_RELEASE = 'MEDIA_STUDIO_BUDGET_RELEASE';
    case MEDIA_IP_LICENSING_FEE = 'MEDIA_IP_LICENSING_FEE';
    case MEDIA_BOX_OFFICE_DISTRIBUTE = 'MEDIA_BOX_OFFICE_DISTRIBUTE';
    case MEDIA_TALENT_RESIDUAL_PAYOUT = 'MEDIA_TALENT_RESIDUAL_PAYOUT';
}
