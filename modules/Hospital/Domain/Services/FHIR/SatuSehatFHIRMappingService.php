<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services\FHIR;

use Carbon\CarbonImmutable;
use Modules\Hospital\Application\DTOs\FHIRBundlePayloadDTO;
use Modules\Hospital\Domain\Models\Admission;

final class SatuSehatFHIRMappingService
{
    private string $organizationId;

    public function __construct(string $organizationId = '10000004')
    {
        $this->organizationId = $organizationId;
    }

    /**
     * Memetakan admisi rawat inap ke Resource Encounter FHIR R4.
     *
     * @return array<string, mixed>
     */
    public function mapEncounter(Admission $admission): array
    {
        $patient = $admission->patient;
        $ihsPatientId = $patient?->nik ? 'P'.substr($patient->nik, -8) : 'P-UNKNOWN';

        return [
            'resourceType' => 'Encounter',
            'id' => 'encounter-'.$admission->id,
            'identifier' => [
                [
                    'system' => "http://sys-ids.kemkes.go.id/encounter/{$this->organizationId}",
                    'value' => 'ADM-'.$admission->id,
                ],
            ],
            'status' => $this->mapEncounterStatus($admission->status),
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'IMP',
                'display' => 'inpatient encounter',
            ],
            'subject' => [
                'reference' => "Patient/{$ihsPatientId}",
                'display' => $patient?->name ?? 'Pasien Rawat Inap',
            ],
            'period' => [
                'start' => CarbonImmutable::parse($admission->admission_time)->toIso8601String(),
                'end' => $admission->discharge_time ? CarbonImmutable::parse($admission->discharge_time)->toIso8601String() : null,
            ],
            'serviceProvider' => [
                'reference' => "Organization/{$this->organizationId}",
            ],
        ];
    }

    /**
     * Memetakan observasi tanda vital & NEWS2 ke Resource Observation FHIR R4.
     *
     * @param  array<string, mixed>  $vitals
     * @return array<string, mixed>
     */
    public function mapNews2Observation(int $admissionId, int $patientId, array $vitals, int $news2Score): array
    {
        return [
            'resourceType' => 'Observation',
            'id' => 'obs-news2-'.uniqid(),
            'status' => 'final',
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                            'code' => 'survey',
                            'display' => 'Survey',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://loinc.org',
                        'code' => '96552-5',
                        'display' => 'National Early Warning Score 2',
                    ],
                ],
            ],
            'subject' => [
                'reference' => "Patient/P-{$patientId}",
            ],
            'encounter' => [
                'reference' => "Encounter/encounter-{$admissionId}",
            ],
            'effectiveDateTime' => CarbonImmutable::now()->toIso8601String(),
            'valueInteger' => $news2Score,
            'component' => [
                [
                    'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8867-4', 'display' => 'Heart rate']]],
                    'valueQuantity' => ['value' => $vitals['heart_rate'] ?? 80, 'unit' => 'beats/minute', 'system' => 'http://unitsofmeasure.org', 'code' => '/min'],
                ],
                [
                    'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']]],
                    'valueQuantity' => ['value' => $vitals['systolic_bp'] ?? 120, 'unit' => 'mm[Hg]', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]'],
                ],
                [
                    'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '59408-5', 'display' => 'Oxygen saturation in Arterial blood']]],
                    'valueQuantity' => ['value' => $vitals['spo2'] ?? 98.0, 'unit' => '%', 'system' => 'http://unitsofmeasure.org', 'code' => '%'],
                ],
            ],
        ];
    }

    /**
     * Memetakan diagnosis ke Resource Condition FHIR R4.
     *
     * @return array<string, mixed>
     */
    public function mapCondition(int $admissionId, int $patientId, string $icd10Code, string $icd10Name): array
    {
        return [
            'resourceType' => 'Condition',
            'id' => 'cond-'.uniqid(),
            'clinicalStatus' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                        'code' => 'active',
                        'display' => 'Active',
                    ],
                ],
            ],
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/condition-category',
                            'code' => 'encounter-diagnosis',
                            'display' => 'Encounter Diagnosis',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://hl7.org/fhir/sid/icd-10',
                        'code' => $icd10Code,
                        'display' => $icd10Name,
                    ],
                ],
            ],
            'subject' => [
                'reference' => "Patient/P-{$patientId}",
            ],
            'encounter' => [
                'reference' => "Encounter/encounter-{$admissionId}",
            ],
            'recordedDate' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Membentuk FHIR Bundle Transaction untuk sinkronisasi SatuSehat.
     *
     * @param  list<array<string, mixed>>  $resources
     */
    public function createBundle(string $bundleId, array $resources): FHIRBundlePayloadDTO
    {
        return new FHIRBundlePayloadDTO(
            bundleType: 'transaction',
            bundleIdentifier: $bundleId,
            timestamp: CarbonImmutable::now()->toIso8601String(),
            totalResources: count($resources),
            resources: $resources
        );
    }

    private function mapEncounterStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'ACTIVE', 'ADMITTED', 'BED_OCCUPIED' => 'in-progress',
            'DISCHARGED', 'COMPLETED' => 'finished',
            'CANCELLED' => 'cancelled',
            default => 'planned',
        };
    }
}
