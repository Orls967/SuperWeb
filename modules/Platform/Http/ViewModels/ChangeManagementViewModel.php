<?php

declare(strict_types=1);

namespace Modules\Platform\Http\ViewModels;

use Illuminate\Support\Collection;
use Modules\Platform\Domain\Models\ChangeRequest;

final class ChangeManagementViewModel
{
    /**
     * @param  Collection<int, ChangeRequest>  $changes
     */
    public function __construct(
        public readonly Collection $changes
    ) {}

    public function totalChanges(): int
    {
        return $this->changes->count();
    }

    public function highRiskCount(): int
    {
        return $this->changes->filter(fn (ChangeRequest $cr): bool => in_array($cr->risk_tier, ['high', 'critical'], true))->count();
    }

    public function pendingCabReviewCount(): int
    {
        return $this->changes->where('status', 'cab_review')->count();
    }
}
