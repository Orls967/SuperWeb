<?php

declare(strict_types=1);

namespace Modules\Wms\Application\Services;

use Modules\Logistics\Contracts\CarrierCutoffProvider;

/**
 * Orkestrasi jadwal dock bay & antrean pengiriman berdasarkan cut-off kurir.
 */
final class WmsDockAndCutoffService
{
    public function __construct(
        private readonly DockAppointmentScheduler $scheduler,
        private readonly CarrierCutoffProvider $cutoffProvider,
    ) {}

    /**
     * @param  list<array{carrier_code: string, dock_bay: string, start_minute: int, duration_minutes: int}>  $requests
     * @return array{scheduled: list<array<string, mixed>>, cutoffs: list<array<string, mixed>>}
     */
    public function scheduleBatch(string $warehouseCode, array $requests): array
    {
        $existing = [];
        $scheduled = [];

        foreach ($requests as $req) {
            $slot = $this->scheduler->scheduleSlot(
                warehouseCode: $warehouseCode,
                carrierCode: $req['carrier_code'],
                dockBay: $req['dock_bay'],
                startMinute: (int) $req['start_minute'],
                durationMinutes: (int) $req['duration_minutes'],
                existingSlots: $existing,
            );
            $existing[] = [
                'dock_bay' => $slot['dock_bay'],
                'start_minute' => $slot['start_minute'],
                'end_minute' => $slot['end_minute'],
            ];
            $scheduled[] = $slot;
        }

        return [
            'scheduled' => $scheduled,
            'cutoffs' => $this->cutoffProvider->getCutoffsForWarehouse($warehouseCode),
        ];
    }
}
