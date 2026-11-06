<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

/**
 * Nilai hasil komputasi efisiensi termal chiller dan sertifikat verifikasi.
 */
final class ChillerCopResult
{
    public function __construct(
        public readonly int $coolingTons,
        public readonly int $electricalPowerKw,
        public readonly int $thermalOutputKw,
        public readonly int $actualKwPerTonBps,
        public readonly int $copBps,
        public readonly int $deviationBps,
        public readonly int $chilledWaterFlowLps,
        public readonly int $deltaTempTenthCelsius,
        public readonly string $operationalStatus,
        public readonly string $certificateHash,
        public readonly string $canonicalPayload,
    ) {}

    /**
     * Konversi ke array untuk serialisasi JSON / database metadata.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cooling_tons' => $this->coolingTons,
            'electrical_power_kw' => $this->electricalPowerKw,
            'thermal_output_kw' => $this->thermalOutputKw,
            'actual_kw_per_ton_bps' => $this->actualKwPerTonBps,
            'cop_bps' => $this->copBps,
            'deviation_bps' => $this->deviationBps,
            'chilled_water_flow_lps' => $this->chilledWaterFlowLps,
            'delta_temp_tenth_celsius' => $this->deltaTempTenthCelsius,
            'operational_status' => $this->operationalStatus,
            'certificate_hash' => $this->certificateHash,
            'canonical_payload' => $this->canonicalPayload,
        ];
    }
}
