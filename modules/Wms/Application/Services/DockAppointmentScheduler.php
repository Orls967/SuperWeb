<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use DomainException;
use Modules\Logistics\Contracts\CarrierCutoffProvider;

/**
 * Penjadwalan dock appointment & validasi cut-off kurir (integrasi CarrierCutoffProvider).
 */
final class DockAppointmentScheduler
{
    public function __construct(
        private readonly CarrierCutoffProvider $cutoffProvider,
    ) {}

    /**
     * @param  list<array{dock_bay: string, start_minute: int, end_minute: int}>  $existingSlots
     * @return array{dock_bay: string, start_minute: int, end_minute: int, carrier_code: string, within_cutoff: bool}
     */
    public function scheduleSlot(
        string $warehouseCode,
        string $carrierCode,
        string $dockBay,
        int $startMinute,
        int $durationMinutes,
        array $existingSlots,
    ): array {
        $endMinute = $startMinute + $durationMinutes;
        if ($durationMinutes <= 0 || $startMinute < 0 || $endMinute > 1440) {
            throw new DomainException('Slot waktu dock tidak valid.');
        }

        foreach ($existingSlots as $slot) {
            if ($slot['dock_bay'] === $dockBay && $startMinute < $slot['end_minute'] && $endMinute > $slot['start_minute']) {
                throw new DomainException("Terjadi bentrok jadwal pada dock {$dockBay}.");
            }
        }

        $cutoffs = $this->cutoffProvider->getCutoffsForWarehouse($warehouseCode);
        $withinCutoff = true;
        foreach ($cutoffs as $c) {
            if (strtoupper($c['carrier_code']) === strtoupper($carrierCode) && $c['is_closed']) {
                $withinCutoff = false;
                break;
            }
        }

        return [
            'dock_bay' => $dockBay,
            'start_minute' => $startMinute,
            'end_minute' => $endMinute,
            'carrier_code' => strtoupper($carrierCode),
            'within_cutoff' => $withinCutoff,
        ];
    }
}
