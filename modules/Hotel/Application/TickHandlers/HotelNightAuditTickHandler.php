<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\TickHandlers;

use Carbon\CarbonInterface;
use Modules\Core\Contracts\SimTickHandlerInterface;
use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Handler simulasi harian untuk modul Hotel: mengeksekusi night audit,
 * pembebanan tarif kamar harian otomatis, dan kalkulasi ADR / RevPAR deterministik.
 */
class HotelNightAuditTickHandler implements SimTickHandlerInterface
{
    public const TOTAL_ROOM_INVENTORY = 50;

    public const STANDARD_ROOM_RATE_IDR = 750000;

    public function name(): string
    {
        return 'hotel_night_audit';
    }

    /**
     * @return list<string>
     */
    public function dependencies(): array
    {
        return ['hospital_discharge']; // Berjalan setelah hospital discharge
    }

    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool
    {
        return true;
    }

    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        $checkedInReservations = HotelReservation::where('status', 'checked_in')->get();
        $occupiedRooms = $checkedInReservations->count();

        $totalRoomRevenue = 0;
        $totalTaxAccrued = 0;

        foreach ($checkedInReservations as $res) {
            $dailyRate = (int) ($res->daily_rate ?? self::STANDARD_ROOM_RATE_IDR);
            $taxAmount = (int) ($dailyRate * 0.10); // Pajak hotel 10%

            $totalRoomRevenue += $dailyRate;
            $totalTaxAccrued += $taxAmount;

            // Catat akumulasi pembebanan kamar jika ada kolom terkait
            if (isset($res->total_charges)) {
                $res->total_charges += ($dailyRate + $taxAmount);
                $res->save();
            }
        }

        $occupancyPct = round(($occupiedRooms / self::TOTAL_ROOM_INVENTORY) * 100, 2);
        $adr = $occupiedRooms > 0 ? round($totalRoomRevenue / $occupiedRooms, 2) : 0.0;
        $revpar = round($totalRoomRevenue / self::TOTAL_ROOM_INVENTORY, 2);

        return [
            'day' => $dayNumber,
            'virtual_date' => $virtualNow->toDateString(),
            'rooms_occupied' => $occupiedRooms,
            'occupancy_pct' => $occupancyPct,
            'total_room_revenue_idr' => $totalRoomRevenue,
            'total_tax_accrued_idr' => $totalTaxAccrued,
            'average_daily_rate_idr' => $adr,
            'revpar_idr' => $revpar,
            'status' => 'ok',
        ];
    }
}
