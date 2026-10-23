<?php

declare(strict_types=1);

namespace Modules\Med\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum MedTransactionTypes: string implements TransactionTypeRegistry
{
    case MEDIA_AD_CAMPAIGN_SETTLEMENT = 'MEDIA_AD_CAMPAIGN_SETTLEMENT';
    case MEDIA_TALENT_ROYALTY_ACCRUAL = 'MEDIA_TALENT_ROYALTY_ACCRUAL';
}
