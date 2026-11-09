<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class BedAssignmentCriteriaDTO
{
    /**
     * @param array<string, mixed> $requiredEquipment
     * @param list<string> $clinicalFlags
     */
    public function __construct(
        public readonly int $patientId,
        public readonly string $gender,
        public readonly int $ageYears,
        public readonly string $requiredClass,
        public readonly string $acuityLevel,
        public readonly bool $requiresIsolation,
        public readonly ?string $isolationType = null,
        public readonly array $requiredEquipment = [],
        public readonly list<string> $clinicalFlags = [],
        public readonly ?string $preferredWard = null,
        public readonly ?string $sourceDepartment = 'IGD'
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            patientId: (int) ($data['patient_id'] ?? 0),
            gender: (string) ($data['gender'] ?? 'UNKNOWN'),
            ageYears: (int) ($data['age_years'] ?? 0),
            requiredClass: (string) ($data['required_class'] ?? 'CLASS_3'),
            acuityLevel: (string) ($data['acuity_level'] ?? 'STABLE'),
            requiresIsolation: (bool) ($data['requires_isolation'] ?? false),
            isolationType: isset($data['isolation_type']) ? (string) $data['isolation_type'] : null,
            requiredEquipment: (array) ($data['required_equipment'] ?? []),
            clinicalFlags: (array) ($data['clinical_flags'] ?? []),
            preferredWard: isset($data['preferred_ward']) ? (string) $data['preferred_ward'] : null,
            sourceDepartment: isset($data['source_department']) ? (string) $data['source_department'] : 'IGD'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'patient_id' => $this->patientId,
            'gender' => $this->gender,
            'age_years' => $this->ageYears,
            'required_class' => $this->requiredClass,
            'acuity_level' => $this->acuityLevel,
            'requires_isolation' => $this->requiresIsolation,
            'isolation_type' => $this->isolationType,
            'required_equipment' => $this->requiredEquipment,
            'clinical_flags' => $this->clinicalFlags,
            'preferred_ward' => $this->preferredWard,
            'source_department' => $this->sourceDepartment,
        ];
    }
}
