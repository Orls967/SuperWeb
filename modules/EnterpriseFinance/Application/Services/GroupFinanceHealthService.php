<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Modules\EnterpriseFinance\Domain\Models\ControlTestRun;
use Modules\EnterpriseFinance\Domain\Models\FinancialPeriodLock;
use Modules\EnterpriseFinance\Domain\Models\SodException;
use Psr\Clock\ClockInterface;

class GroupFinanceHealthService
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Menghitung skor kesehatan dan metrik observabilitas keuangan grup (D54-9).
     *
     * @return array{
     *     overall_health_score: int,
     *     status: string,
     *     closed_periods_count: int,
     *     active_sod_exceptions_count: int,
     *     deficient_controls_count: int,
     *     overdue_deadlines_count: int,
     *     timestamp: string
     * }
     */
    public function assessGroupHealth(): array
    {
        $closedPeriods = FinancialPeriodLock::query()->where('is_locked', true)->count();
        $activeExceptions = SodException::query()
            ->where('is_active', true)
            ->where('expires_at', '>', $this->now())
            ->count();

        $deficientControls = ControlTestRun::query()
            ->whereIn('status', ['DEFICIENT', 'FAILED'])
            ->where('tested_at', '>=', $this->now()->subDays(7))
            ->count();

        // Skor penalti kesehatan (100 = sempurna)
        $score = 100;
        $score -= ($deficientControls * 15);
        $score = max(10, min(100, $score));

        $status = 'HEALTHY';
        if ($score < 60) {
            $status = 'CRITICAL';
        } elseif ($score < 80) {
            $status = 'WARNING';
        }

        return [
            'overall_health_score' => $score,
            'status' => $status,
            'closed_periods_count' => $closedPeriods,
            'active_sod_exceptions_count' => $activeExceptions,
            'deficient_controls_count' => $deficientControls,
            'overdue_deadlines_count' => 0,
            'timestamp' => $this->now()->toIso8601String(),
        ];
    }
}

