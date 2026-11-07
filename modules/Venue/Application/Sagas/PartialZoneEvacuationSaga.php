<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Sagas;

use Illuminate\Support\Facades\DB;
use Modules\Venue\Application\Services\PartialWeatherZoneRelocationService;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\VenueSaga;

class PartialZoneEvacuationSaga
{
    public function __construct(
        private readonly PartialWeatherZoneRelocationService $relocationService
    ) {}

    /**
     * @return array{
     *     saga_id: string,
     *     status: string,
     *     relocated_patrons: int,
     *     refunded_patrons: int,
     *     compensation_executed: bool
     * }
     */
    public function execute(
        EntertainmentEvent $event,
        EntertainmentZone $evacuatedZone,
        EntertainmentZone $safeTargetZone,
        string $reason
    ): array {
        $sagaId = 'SAGA-EVAC-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));

        return DB::transaction(function () use ($event, $evacuatedZone, $safeTargetZone, $reason, $sagaId) {
            $saga = VenueSaga::create([
                'saga_id' => $sagaId,
                'saga_type' => 'PARTIAL_ZONE_EVACUATION',
                'event_id' => $event->id,
                'status' => 'STARTED',
                'payload' => [
                    'from_zone' => $evacuatedZone->name,
                    'to_zone' => $safeTargetZone->name,
                    'reason' => $reason,
                ],
            ]);

            try {
                // Step 1: Kunci zona yang dievakuasi
                $evacuatedZone->crowd_lockdown_active = true;
                $evacuatedZone->save();

                // Step 2: Alihkan pemegang tiket dan posting kompensasi
                $result = $this->relocationService->relocatePatronsFromClosedZone(
                    event: $event,
                    closedZone: $evacuatedZone,
                    targetIndoorZone: $safeTargetZone,
                    partialRefundBps: 2500 // 25% kompensasi
                );

                $saga->status = 'COMPLETED';
                $saga->payload = array_merge($saga->payload ?? [], $result);
                $saga->save();

                return [
                    'saga_id' => $sagaId,
                    'status' => 'COMPLETED',
                    'relocated_patrons' => $result['relocated_count'],
                    'refunded_patrons' => $result['refunded_count'],
                    'compensation_executed' => true,
                ];
            } catch (\Throwable $e) {
                // Kompensasi saga: Lepaskan lockdown dan batalkan
                $evacuatedZone->crowd_lockdown_active = false;
                $evacuatedZone->save();

                $saga->status = 'FAILED';
                $saga->compensation_log = [
                    'error' => $e->getMessage(),
                    'compensated_at' => now()->toIso8601String(),
                ];
                $saga->save();

                throw $e;
            }
        });
    }
}
