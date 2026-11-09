<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Services\ClinicalDecisionSupportRuleEngine;
use Modules\Hospital\Domain\ValueObjects\MedicineDosageRegimen;
use Modules\Hospital\Domain\ValueObjects\PrescriptionSafetyScore;

class EvaluatePrescriptionSafetyAction
{
    public function __construct(
        protected ClinicalDecisionSupportRuleEngine $cdsEngine
    ) {}

    /**
     * @param array<int, array{
     *     drug_code: string,
     *     drug_name: string,
     *     dose_mg: float,
     *     times_per_day: int,
     *     duration_days: int,
     *     max_safe_daily_mg: float
     * }> $items
     */
    public function execute(
        Patient $patient,
        array $items,
        ?float $serumCreatinineMgDl = null,
        ?float $patientWeightKg = 70.0
    ): PrescriptionSafetyScore {
        $regimens = [];
        foreach ($items as $item) {
            $regimens[] = new MedicineDosageRegimen(
                drugCode: $item['drug_code'],
                drugName: $item['drug_name'],
                dosePerAdministrationMg: (float) $item['dose_mg'],
                administrationsPerDay: (int) $item['times_per_day'],
                durationDays: (int) $item['duration_days'],
                maxSafeDailyDoseMg: (float) $item['max_safe_daily_mg']
            );
        }

        return $this->cdsEngine->evaluateSafety(
            patient: $patient,
            regimens: $regimens,
            serumCreatinineMgDl: $serumCreatinineMgDl,
            patientWeightKg: $patientWeightKg
        );
    }
}
