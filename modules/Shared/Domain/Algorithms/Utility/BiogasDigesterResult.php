<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

/**
 * Hasil komputasi konversi biomassa biogas WTE dan sertifikat verifikasi.
 */
final class BiogasDigesterResult
{
    public function __construct(
        public readonly int $wasteTonnageKg,
        public readonly int $totalSolidsKg,
        public readonly int $volatileSolidsKg,
        public readonly int $degradedVsKg,
        public readonly int $biogasYieldM3,
        public readonly int $methaneM3,
        public readonly int $thermalEnergyMj,
        public readonly int $electricGrossKwh,
        public readonly int $avoidedEmissionCo2eKg,
        public readonly string $certificateHash,
        public readonly string $canonicalPayload,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'waste_tonnage_kg' => $this->wasteTonnageKg,
            'total_solids_kg' => $this->totalSolidsKg,
            'volatile_solids_kg' => $this->volatileSolidsKg,
            'degraded_vs_kg' => $this->degradedVsKg,
            'biogas_yield_m3' => $this->biogasYieldM3,
            'methane_m3' => $this->methaneM3,
            'thermal_energy_mj' => $this->thermalEnergyMj,
            'electric_gross_kwh' => $this->electricGrossKwh,
            'avoided_emission_co2e_kg' => $this->avoidedEmissionCo2eKg,
            'certificate_hash' => $this->certificateHash,
            'canonical_payload' => $this->canonicalPayload,
        ];
    }
}
