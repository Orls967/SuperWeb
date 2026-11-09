<?php

declare(strict_types=1);

namespace Modules\Platform\Http\ViewModels;

use Illuminate\Support\Collection;
use Modules\Platform\Domain\Models\PhaseMetricSnapshot;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\Models\ServiceSlo;

final class DevPortalDashboardViewModel
{
    /**
     * @param Collection<int, SandboxEnvironment> $sandboxes
     * @param Collection<int, PhaseMetricSnapshot> $metrics
     * @param Collection<int, ServiceSlo> $slos
     */
    public function __construct(
        public readonly Collection $sandboxes,
        public readonly Collection $metrics,
        public readonly Collection $slos
    ) {
    }

    public function activeSandboxCount(): int
    {
        return $this->sandboxes->filter(fn (SandboxEnvironment $s) => $s->status === 'ready')->count();
    }

    public function compliantMetricCount(): int
    {
        return $this->metrics->filter(fn (PhaseMetricSnapshot $m) => $m->status === 'compliant')->count();
    }

    public function criticalSloCount(): int
    {
        return $this->slos->filter(fn (ServiceSlo $s) => $s->status === 'critical')->count();
    }
}

