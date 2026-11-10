<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final class ConsolidatedTrialBalanceInputDTO
{
    /**
     * @param  list<EntityTrialBalanceDTO>  $entities
     */
    public function __construct(
        public readonly string $period,
        public readonly string $reportingCurrency,
        public readonly array $entities
    ) {}
}
