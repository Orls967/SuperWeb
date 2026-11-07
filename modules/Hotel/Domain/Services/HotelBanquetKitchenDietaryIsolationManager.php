<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

class HotelBanquetKitchenDietaryIsolationManager
{
    /**
     * Memverifikasi pemisahan stasiun masak khusus hidangan Halal, Kosher, dan Bebas Alergen Kacang (Dietary Isolation Protocol).
     *
     * @return array{contract_id: string, dedicated_fryers_count: int, dedicated_cutting_boards_count: int, cross_contamination_risk: string, isolation_certified: bool}
     */
    public function verifyKitchenIsolation(
        string $contractId,
        int $dedicatedFryers,
        int $dedicatedCuttingBoards,
        bool $requiresAllergenFreeStation = true
    ): array {
        $isSafe = ($dedicatedFryers >= 1) && ($dedicatedCuttingBoards >= 2);
        $risk = $isSafe ? 'NEGLIGIBLE_CONTROLLED' : 'CROSS_CONTAMINATION_WARNING';

        return [
            'contract_id' => $contractId,
            'dedicated_fryers_count' => $dedicatedFryers,
            'dedicated_cutting_boards_count' => $dedicatedCuttingBoards,
            'cross_contamination_risk' => $risk,
            'isolation_certified' => $isSafe,
        ];
    }
}
