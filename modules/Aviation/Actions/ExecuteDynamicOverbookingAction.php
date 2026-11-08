<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Services\AviationOverbookingManagementService;
use Modules\Aviation\Domain\Models\AviationFlight;

class ExecuteDynamicOverbookingAction
{
    public function __construct(
        protected AviationOverbookingManagementService $overbookingService
    ) {}

    /**
     * Terapkan alokasi overbooking optimal pada penerbangan komersial terjadwal.
     *
     * @return array{flight_id: int, original_capacity: int, updated_capacity: int, additional_seats: int}
     */
    public function execute(int $flightId, float $historicalNoShowRate = 0.05): array
    {
        return DB::transaction(function () use ($flightId, $historicalNoShowRate) {
            $flight = AviationFlight::lockForUpdate()->findOrFail($flightId);

            if (in_array($flight->status, ['departed', 'arrived', 'cancelled'], true)) {
                throw new DomainException(sprintf('Penerbangan [%s] berstatus [%s] tidak dapat di-overbook.', $flight->flight_number, $flight->status));
            }

            $res = $this->overbookingService->applyOverbookingToFlight($flight->id, $historicalNoShowRate);

            return [
                'flight_id' => $flight->id,
                'original_capacity' => $res['physical_capacity'],
                'updated_capacity' => $res['optimal_booking_limit'],
                'additional_seats' => $res['overbooking_seats'],
            ];
        });
    }
}
