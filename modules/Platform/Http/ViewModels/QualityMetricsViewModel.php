<?php

declare(strict_types=1);

namespace Modules\Platform\Http\ViewModels;

use Illuminate\Support\Collection;
use Modules\Platform\Domain\Models\PhaseMetricSnapshot;

final class QualityMetricsViewModel
{
    /**
     * @param Collection<int, PhaseMetricSnapshot> $snapshots
     */
    public function __construct(
        public readonly Collection $snapshots
    ) {
    }

    public function averageDensity(): float
    {
        if ($this->snapshots->isEmpty()) {
            return 0.0;
        }

        return round((float) $this->snapshots->avg('decision_density'), 4);
    }
}

