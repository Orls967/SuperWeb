<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final class EntityTrialBalanceDTO
{
    /**
     * @param  list<AccountBalanceDTO>  $accountBalances
     */
    public function __construct(
        public readonly string $entityCode,
        public readonly string $entityName,
        public readonly string $functionalCurrency,
        public readonly array $accountBalances
    ) {}
}
