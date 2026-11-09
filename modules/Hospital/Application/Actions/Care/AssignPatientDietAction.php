<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Carbon\CarbonImmutable;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class AssignPatientDietAction
{
    private PatientPassportService $passportService;

    public function __construct(PatientPassportService $passportService)
    {
        $this->passportService = $passportService;
    }

    /**
     * @param array{
     *     admission_id: int,
     *     diet_type: string,
     *     calories_kcal: int,
     *     protein_grams: int,
     *     restrictions: list<string>,
     *     special_instructions?: string|null,
     *     assigned_by_user_id: int
     * } $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $admission = Admission::findOrFail($data['admission_id']);

        $dietOrder = [
            'diet_type' => $data['diet_type'],
            'calories_kcal' => $data['calories_kcal'],
            'protein_grams' => $data['protein_grams'],
            'restrictions' => $data['restrictions'],
            'special_instructions' => $data['special_instructions'] ?? null,
            'assigned_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $currentMeta = is_array($admission->metadata) ? $admission->metadata : (json_decode($admission->metadata ?? '[]', true) ?: []);
        $currentMeta['clinical_diet'] = $dietOrder;
        $admission->metadata = $currentMeta;
        $admission->save();

        $this->passportService->appendEvent(
            patientId: $admission->patient_id,
            eventType: 'CLINICAL_DIET_ASSIGNED',
            eventPayload: $dietOrder,
            actorId: $data['assigned_by_user_id']
        );

        return [
            'success' => true,
            'admission_id' => $admission->id,
            'diet_order' => $dietOrder,
        ];
    }
}
