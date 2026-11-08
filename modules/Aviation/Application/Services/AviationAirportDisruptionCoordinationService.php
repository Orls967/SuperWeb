<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Facades\Log;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;

class AviationAirportDisruptionCoordinationService
{
    /**
     * Koordinasikan evakuasi darat dan penanganan bagasi penumpang saat pembatalan penerbangan.
     *
     * @return array{case_id: int, buses_requested: int, bags_tagged_for_transfer: int, perishable_cargo_refrigirated: int}
     */
    public function coordinateGroundResponse(int $disruptionCaseId): array
    {
        $case = AviationDisruptionCase::with(['flight', 'passengers'])->findOrFail($disruptionCaseId);

        $paxCount = $case->passengers->count();
        // 1 bus kapasitas 40 penumpang
        $busesNeeded = (int) ceil($paxCount / 40.0);

        // Estimasi bagasi tercatat rata-rata 1.2 bag per pax
        $estimatedBags = (int) round($paxCount * 1.2);

        // Amankan kargo udara perishable/DG pada penerbangan yang dibatalkan
        $cargoItems = AviationCargoConsignment::where('flight_id', $case->flight_id)->get();
        $perishableCount = 0;

        foreach ($cargoItems as $cargo) {
            if (str_contains(strtolower($cargo->nature_of_goods ?? ''), 'perishable') || $cargo->is_dg) {
                $cargo->status = 'stored_cold_storage';
                $cargo->save();
                $perishableCount++;
            }
        }

        Log::info('Airport Ground Disruption Response Coordinated', [
            'case_number' => $case->case_number,
            'passengers' => $paxCount,
            'buses' => $busesNeeded,
            'bags' => $estimatedBags,
            'cold_storage_cargo' => $perishableCount,
        ]);

        return [
            'case_id' => $case->id,
            'buses_requested' => $busesNeeded,
            'bags_tagged_for_transfer' => $estimatedBags,
            'perishable_cargo_refrigirated' => $perishableCount,
        ];
    }
}
