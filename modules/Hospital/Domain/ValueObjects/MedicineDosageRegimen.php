<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\ValueObjects;

final class MedicineDosageRegimen
{
    public function __construct(
        public readonly string $drugCode,
        public readonly string $drugName,
        public readonly float $dosePerAdministrationMg,
        public readonly int $administrationsPerDay,
        public readonly int $durationDays,
        public readonly float $maxSafeDailyDoseMg,
        public readonly string $routeOfAdministration = 'ORAL'
    ) {}

    public function totalDailyDoseMg(): float
    {
        return $this->dosePerAdministrationMg * $this->administrationsPerDay;
    }

    public function totalCourseDoseMg(): float
    {
        return $this->totalDailyDoseMg() * $this->durationDays;
    }

    public function isMaxDailyDoseExceeded(): bool
    {
        return $this->totalDailyDoseMg() > $this->maxSafeDailyDoseMg;
    }

    public function excessRatio(): float
    {
        if ($this->maxSafeDailyDoseMg <= 0) {
            return 1.0;
        }

        return round($this->totalDailyDoseMg() / $this->maxSafeDailyDoseMg, 2);
    }
}
