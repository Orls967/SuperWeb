<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class InaCbgCalculationDTO
{
    /**
     * @param  array  $secondaryDiagnosisCodes
     * @param  array  $procedureCodes
     *
     * @phpstan-param list<string> $secondaryDiagnosisCodes
     * @phpstan-param list<string> $procedureCodes
     */
    public function __construct(
        public readonly string $cbgCode,
        public readonly string $cbgDescription,
        public readonly string $severityLevel,
        public readonly int $baseTariffIdr,
        public readonly int $entitledClassTariffIdr,
        public readonly int $actualClassTariffIdr,
        public readonly int $classUpgradeExcessIdr,
        public readonly string $primaryDiagnosisCode,
        public readonly array $secondaryDiagnosisCodes = [],
        public readonly array $procedureCodes = [],
        public readonly bool $isTopUpCapped = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cbg_code' => $this->cbgCode,
            'cbg_description' => $this->cbgDescription,
            'severity_level' => $this->severityLevel,
            'base_tariff_idr' => $this->baseTariffIdr,
            'entitled_class_tariff_idr' => $this->entitledClassTariffIdr,
            'actual_class_tariff_idr' => $this->actualClassTariffIdr,
            'class_upgrade_excess_idr' => $this->classUpgradeExcessIdr,
            'primary_diagnosis_code' => $this->primaryDiagnosisCode,
            'secondary_diagnosis_codes' => $this->secondaryDiagnosisCodes,
            'procedure_codes' => $this->procedureCodes,
            'is_top_up_capped' => $this->isTopUpCapped,
        ];
    }
}
