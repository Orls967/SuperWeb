<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hospital\Domain\Algorithms\HospitalInfectionControlRiskScorer;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\HospitalBed;

final class HospitalInfectionSurveillanceService
{
    private HospitalInfectionControlRiskScorer $scorer;

    public function __construct(HospitalInfectionControlRiskScorer $scorer)
    {
        $this->scorer = $scorer;
    }

    /**
     * Memindai risiko infeksi ruang rawat dan mengevaluasi kesesuaian isolasi ranjang.
     *
     * @param array<string, mixed> $patientClinicalProfile
     * @return array<string, mixed>
     */
    public function auditAdmissionInfectionControl(Admission $admission, array $patientClinicalProfile): array
    {
        $eval = $this->scorer->evaluate($patientClinicalProfile);
        $bed = $admission->bed;

        $isolationCompatible = true;
        $mismatchReason = null;

        if ($eval['recommended_isolation'] === 'AIRBORNE_OR_NEGATIVE_PRESSURE') {
            if (!$bed || $bed->class !== 'ISOLATION') {
                $isolationCompatible = false;
                $mismatchReason = 'Pasien membutuhkan ruang isolasi bertekanan negatif tapi ditempatkan di bangsal non-isolasi.';
            }
        } elseif ($eval['recommended_isolation'] === 'CONTACT_ISOLATION_SINGLE_ROOM') {
            if (!$bed || ($bed->class !== 'ISOLATION' && $bed->class !== 'VIP')) {
                $isolationCompatible = false;
                $mismatchReason = 'Pasien membutuhkan contact precaution kamar terpisah/single room.';
            }
        }

        return [
            'admission_id' => $admission->id,
            'bed_id' => $bed?->id,
            'bed_code' => $bed?->bed_code,
            'infection_evaluation' => $eval,
            'isolation_compatible' => $isolationCompatible,
            'mismatch_reason' => $mismatchReason,
            'audited_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
