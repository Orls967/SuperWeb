<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\ViewModels;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Aviation\Domain\Models\AviationLoyaltyAccount;

class LoyaltyProgramViewModel
{
    /**
     * @param  LengthAwarePaginator<AviationLoyaltyAccount>  $accounts
     * @param  array<string, int>  $metrics
     */
    public function __construct(
        public LengthAwarePaginator $accounts,
        public array $metrics = []
    ) {}

    public function totalMembers(): int
    {
        return $this->metrics['total_members'] ?? 0;
    }

    public function elitMembersCount(): int
    {
        return $this->metrics['elit_members'] ?? 0;
    }

    public function totalMilesOutstanding(): int
    {
        return $this->metrics['total_miles'] ?? 0;
    }
}
