<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use App\Models\User;
use Modules\Mining\Application\Sagas\FuelAnomalyHoldSaga;

/**
 * Aksi Evaluasi Deviasi Bahan Bakar Kontraktor & Penahanan Pembayaran (D93-5, M3).
 */
class EvaluateContractorFuelAnomalyAction
{
    public function __construct(
        private readonly FuelAnomalyHoldSaga $fuelSaga
    ) {}

    /**
     * @param  array{
     *     site_id: string,
     *     equipment_id: string,
     *     contractor_party_id: string,
     *     shift_date: string,
     *     shift_code: string,
     *     historical_ton_km_thousands: list<float|int>,
     *     historical_fuel_liters: list<float|int>,
     *     actual_ton_km_thousands: float|int,
     *     actual_fuel_liters: float|int,
     *     fuel_rate_per_liter_idr: int
     * }  $params
     * @return array{
     *     is_anomaly: bool,
     *     excess_bps: int,
     *     case_id: string|null,
     *     held_amount_idr: int,
     *     status: string
     * }
     */
    public function execute(array $params, ?User $actor = null): array
    {
        return $this->fuelSaga->execute($params, $actor);
    }
}
