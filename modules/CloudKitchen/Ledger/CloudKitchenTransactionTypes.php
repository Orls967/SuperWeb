<?php

declare(strict_types=1);

namespace Modules\CloudKitchen\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum CloudKitchenTransactionTypes: string implements TransactionTypeRegistry
{
    case CATERING_REFUND = 'catering_refund';
    case HCM_MEAL_PAYROLL_DEDUCTION = 'hcm_meal_payroll_deduction';
    case HCM_CATERING_DEDUCTION = 'hcm_catering_deduction';
    case KITCHEN_PRODUCTION_COGS = 'kitchen_production_cogs';
    case CATERING_REVENUE_SETTLEMENT = 'catering_revenue_settlement';
}
