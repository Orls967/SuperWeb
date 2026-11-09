<?php

declare(strict_types=1);

namespace Modules\Platform\Http\ViewModels;

use Illuminate\Support\Collection;
use Modules\Platform\Domain\Models\ServiceSlo;

final class SloMonitorViewModel
{
    /**
     * @param  Collection<int, ServiceSlo>  $slos
     */
    public function __construct(
        public readonly Collection $slos
    ) {}

    public function averageBurnRate(): float
    {
        if ($this->slos->isEmpty()) {
            return 0.0;
        }

        return round((float) $this->slos->avg('burn_rate_1h'), 2);
    }
}
