<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\FHIR\SatuSehatFHIRMappingService;

final class ExportSatuSehatEncounterBundleAction
{
    private SatuSehatFHIRMappingService $fhirMapper;

    public function __construct(SatuSehatFHIRMappingService $fhirMapper)
    {
        $this->fhirMapper = $fhirMapper;
    }

    /**
     * Mengekspor admisi rawat inap beserta diagnosa dan riwayat observasi ke dalam FHIR Bundle.
     *
     * @param int $admissionId
     * @return array<string, mixed>
     */
    public function execute(int $admissionId): array
    {
        $admission = Admission::with(['patient', 'bed'])->findOrFail($admissionId);

        $resources = [];
        $resources[] = $this->fhirMapper->mapEncounter($admission);

        if (!empty($admission->diagnosis_icd10)) {
            $resources[] = $this->fhirMapper->mapCondition(
                admissionId: $admission->id,
                patientId: $admission->patient_id,
                icd10Code: $admission->diagnosis_icd10,
                icd10Name: 'Primary Inpatient Diagnosis'
            );
        }

        $bundleDTO = $this->fhirMapper->createBundle('BUNDLE-ADM-' . $admission->id, $resources);

        return [
            'success' => true,
            'admission_id' => $admission->id,
            'bundle_id' => $bundleDTO->bundleIdentifier,
            'fhir_bundle' => $bundleDTO->toFHIRJson(),
        ];
    }
}
