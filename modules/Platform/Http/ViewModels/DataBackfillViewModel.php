<?php

declare(strict_types=1);

namespace Modules\Platform\Http\ViewModels;

use Illuminate\Support\Collection;
use Modules\Platform\Domain\Models\DataBackfill;

final class DataBackfillViewModel
{
    /**
     * @param  Collection<int, DataBackfill>  $backfills
     */
    public function __construct(
        public readonly Collection $backfills
    ) {}

    public function runningJobsCount(): int
    {
        return $this->backfills->where('status', 'in_progress')->count();
    }
}
