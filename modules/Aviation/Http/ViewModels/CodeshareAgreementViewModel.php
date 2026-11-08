<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\ViewModels;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;

class CodeshareAgreementViewModel
{
    /**
     * @param  LengthAwarePaginator<AviationCodeshareAgreement>  $agreements
     * @param  array<string, int>  $metrics
     */
    public function __construct(
        public LengthAwarePaginator $agreements,
        public array $metrics = []
    ) {}

    public function activeAgreementsCount(): int
    {
        return $this->metrics['active'] ?? 0;
    }

    public function totalCodeshareRoutes(): int
    {
        return $this->metrics['total_routes'] ?? 0;
    }
}
