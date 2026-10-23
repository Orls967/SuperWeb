<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Support;

/**
 * Standard Chart of Accounts registry and template patterns (KONSEP.md §A14.5).
 */
final class ChartOfAccounts
{
    // ESG Carbon Accounts
    public const string ESG_CARBON_CREDIT_INVENTORY = 'esg:carbon_credit_inventory:IDR';

    public const string ESG_CARBON_OFFSET_EXPENSE = 'esg:carbon_offset_expense:IDR';

    // Dynamic Template Account Patterns
    public const string DYNAMIC_GIG_EXPENSE = '*:gig_expense:IDR';

    public const string DYNAMIC_WORKER_PAYABLE = 'payroll:worker_payable:*:IDR';

    /**
     * Recognized authorized dynamic template account patterns.
     *
     * @return list<string>
     */
    public static function dynamicPatterns(): array
    {
        return [
            self::DYNAMIC_GIG_EXPENSE,
            self::DYNAMIC_WORKER_PAYABLE,
        ];
    }
}
