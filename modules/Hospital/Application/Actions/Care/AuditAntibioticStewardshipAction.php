<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Algorithms\AntibioticStewardshipRuleEngine;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class AuditAntibioticStewardshipAction
{
    private AntibioticStewardshipRuleEngine $engine;
    private PatientPassportService $passportService;

    public function __construct(
        AntibioticStewardshipRuleEngine $engine,
        PatientPassportService $passportService
    ) {
        $this->engine = $engine;
        $this->passportService = $passportService;
    }

    /**
     * @param array<string, mixed> $prescription
     * @return array<string, mixed>
     */
    public function execute(int $admissionId, array $prescription, int $actorId): array
    {
        $admission = Admission::findOrFail($admissionId);
        $result = $this->engine->evaluate($prescription);

        $this->passportService->appendEvent(
            patientId: $admission->patient_id,
            eventType: 'ANTIBIOTIC_STEWARDSHIP_AUDITED',
            eventPayload: [
                'admission_id' => $admission->id,
                'antibiotic' => $prescription['antibiotic_name'] ?? 'Unknown',
                'category' => $result['aware_category'],
                'is_allowed' => $result['is_prescription_allowed'],
                'blocking_reasons' => $result['blocking_reasons'],
            ],
            actorId: $actorId
        );

        return [
            'success' => $result['is_prescription_allowed'],
            'admission_id' => $admission->id,
            'evaluation' => $result,
        ];
    }
}
