<?php

declare(strict_types=1);

namespace Modules\Core\Ledger;

use Modules\Shared\Contracts\TransactionTypeRegistry;

enum CoreTransactionTypes: string implements TransactionTypeRegistry
{
    case CROSS_LINE_STABLECOIN_SETTLE = 'CROSS_LINE_STABLECOIN_SETTLE';
    case CROSS_VENUE_FOLIO_CHARGE = 'CROSS_VENUE_FOLIO_CHARGE';
    case GOLDEN_SCENARIO_CONSOLIDATION = 'GOLDEN_SCENARIO_CONSOLIDATION';
    case MEDICAL_TOURISM_SETTLEMENT = 'MEDICAL_TOURISM_SETTLEMENT';
    case UNIVERSAL_INSURANCE_CLAIM = 'UNIVERSAL_INSURANCE_CLAIM';
}
