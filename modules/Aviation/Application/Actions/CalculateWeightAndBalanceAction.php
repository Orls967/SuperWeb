<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Actions;

use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationWeightBalanceRecord;
use Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceChecker;
use Modules\Shared\Domain\Algorithms\Aviation\LoadAndBalanceResult;

class CalculateWeightAndBalanceAction
{
    public function __construct(
        private readonly LoadAndBalanceChecker $checker
    ) {}

    public function execute(
        AviationFlight $flight,
        int $fuelWeightKg,
        int $cargoWeightKg,
        int $paxCount = 100
    ): LoadAndBalanceResult {
        $aircraft = $flight->aircraft;
        if (! $aircraft) {
            throw new \InvalidArgumentException("Penerbangan {$flight->flight_number} belum memiliki pesawat.");
        }

        $oewKg = $aircraft->oew_kg;
        $oewArmMm = 9000;
        $crewWeightKg = 180;
        $crewArmMm = 2500;
        $paxWeightKg = $paxCount * 84;
        $paxArmMm = 7500;
        $cargoArmMm = 8000;
        $fuelArmMm = 9200;

        $stations = [
            ['name' => 'OEW', 'weight_kg' => $oewKg, 'arm_mm' => $oewArmMm],
            ['name' => 'Crew', 'weight_kg' => $crewWeightKg, 'arm_mm' => $crewArmMm],
            ['name' => 'Passengers', 'weight_kg' => $paxWeightKg, 'arm_mm' => $paxArmMm],
            ['name' => 'Cargo', 'weight_kg' => $cargoWeightKg, 'arm_mm' => $cargoArmMm],
        ];

        $result = $this->checker->check(
            mtowKg: $aircraft->mtow_kg,
            cgForwardLimitMm: $aircraft->cg_forward_limit_mm,
            cgAftLimitMm: $aircraft->cg_aft_limit_mm,
            fuelWeightKg: $fuelWeightKg,
            fuelArmMm: $fuelArmMm,
            stations: $stations
        );

        // Simpan rekaman W&B
        AviationWeightBalanceRecord::updateOrCreate(
            ['flight_id' => $flight->id],
            [
                'aircraft_id' => $aircraft->id,
                'total_weight_kg' => $result->totalWeightKg,
                'total_moment' => $result->totalMoment,
                'cg_mm' => $result->cgMm,
                'fuel_weight_kg' => $fuelWeightKg,
                'fuel_arm_mm' => $fuelArmMm,
                'oew_kg' => $oewKg,
                'oew_arm_mm' => $oewArmMm,
                'crew_weight_kg' => $crewWeightKg,
                'crew_arm_mm' => $crewArmMm,
                'pax_weight_kg' => $paxWeightKg,
                'pax_arm_mm' => $paxArmMm,
                'cargo_weight_kg' => $cargoWeightKg,
                'cargo_arm_mm' => $cargoArmMm,
                'is_within_envelope' => $result->isWithinEnvelope,
                'certificate_hash' => $result->certificate->hash(),
                'created_at' => now(),
            ]
        );

        return $result;
    }
}
