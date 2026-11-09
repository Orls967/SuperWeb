<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Algorithms\FallRiskAssessmentEngine;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class AssessPatientFallRiskAction
{
    private FallRiskAssessmentEngine $engine;
    private PatientPassportService $passportService;

    public function __construct(
        FallRiskAssessmentEngine $engine,
        PatientPassportService $passportService
    ) {
        $this->engine = $engine;
        $this->passportService = $passportService;
    }

    /**
     * @param array<string, mixed> $fallRiskProfile
     * @return array<string, mixed>
     */
    public function execute(int $admissionId, array $fallRiskProfile, int $actorId): array
    {
        $admission = Admission::findOrFail($admissionId);
        $result = $this->engine->evaluate($fallRiskProfile);

        $currentMeta = is_array($admission->metadata) ? $admission->metadata : (json_decode($admission->metadata ?? '[]', true) ?: []);
        $currentMeta['fall_risk_assessment'] = $result;
        $admission->metadata = $currentMeta;
        $admission->save();

        $this->passportService->appendEvent(
            patientId: $admission->patient_id,
            eventType: 'FALL_RISK_ASSESSED',
            eventPayload: [
                'admission_id' => $admission->id,
                'morse_score' => $result['morse_score'],
                'category' => $result['risk_category'],
                'bundle' => $result['prevention_bundle'],
            ],
            actorId: $actorId
        );

        return [
            'success' => true,
            'admission_id' => $admission->id,
            'evaluation' => $result,
        ];
    }
}
