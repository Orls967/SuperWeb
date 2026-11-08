<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HtlExhibitionBoothReservation;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelExhibitionTradeShowConnectorService
{
    public function reserveBooth(
        MiceContract $contract,
        string $boothNumber,
        string $boothType,
        int $gridX,
        int $gridY,
        int $widthM = 3,
        int $lengthM = 3,
        float $electricityKva = 0.5,
        bool $isDedicatedLan = false,
        int $baseRentalIdr = 15000000
    ): HtlExhibitionBoothReservation {
        return DB::transaction(function () use (
            $contract,
            $boothNumber,
            $boothType,
            $gridX,
            $gridY,
            $widthM,
            $lengthM,
            $electricityKva,
            $isDedicatedLan,
            $baseRentalIdr
        ) {
            $overlap = HtlExhibitionBoothReservation::where('mice_contract_id', $contract->id)
                ->where('status', '!=', 'CANCELLED')
                ->where(function ($q) use ($gridX, $gridY, $widthM, $lengthM) {
                    $q->where('grid_x', '<', $gridX + $widthM)
                        ->where('grid_x', '>', $gridX - 3)
                        ->where('grid_y', '<', $gridY + $lengthM)
                        ->where('grid_y', '>', $gridY - 3);
                })
                ->exists();

            if ($overlap) {
                throw new DomainException("Booth placement collision detected at grid ({$gridX}, {$gridY}).");
            }

            $utilityCharge = 0;
            if ($electricityKva > 0.0) {
                $utilityCharge += (int) round($electricityKva * 1200000);
            }
            if ($isDedicatedLan) {
                $utilityCharge += 1500000;
            }

            $totalCharge = $baseRentalIdr + $utilityCharge;
            $resCode = 'BTH-'.strtoupper(bin2hex(random_bytes(4)));

            $area = $widthM * $lengthM;
            $footfall = (int) round($area * 55);

            return HtlExhibitionBoothReservation::create([
                'id' => (string) Str::uuid(),
                'reservation_code' => $resCode,
                'mice_contract_id' => $contract->id,
                'booth_number' => $boothNumber,
                'booth_type' => strtoupper($boothType),
                'grid_x' => $gridX,
                'grid_y' => $gridY,
                'width_m' => $widthM,
                'length_m' => $lengthM,
                'electricity_kva' => $electricityKva,
                'is_dedicated_lan' => $isDedicatedLan,
                'base_rental_idr' => $baseRentalIdr,
                'utility_charge_idr' => $utilityCharge,
                'total_charge_idr' => $totalCharge,
                'status' => 'CONFIRMED',
                'estimated_daily_footfall' => $footfall,
            ]);
        });
    }
}
