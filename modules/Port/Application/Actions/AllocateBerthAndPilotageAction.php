<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Port\Application\Services\MarineServicesDisbursementService;
use Modules\Port\Domain\Models\FleetPortCall;

/**
 * Action Alokasi Dermaga, Pandu & Kapal Tunda.
 */
final class AllocateBerthAndPilotageAction
{
    public function __construct(
        private readonly MarineServicesDisbursementService $marineService
    ) {}

    public function execute(
        int $portCallId,
        CarbonImmutable $berthingTime,
        int $estimatedStayHours = 48,
        bool $requireTugAssistance = true
    ): array {
        $portCall = FleetPortCall::with('voyage.vessel')->findOrFail($portCallId);
        $vessel = $portCall->voyage->vessel;

        $loaM = ((float) $vessel->length_overall_cm) / 100.0;
        $draftM = (float) $portCall->arrival_draft_aft_m;
        $unberthingTime = $berthingTime->addHours($estimatedStayHours);

        // Cari jendela tambat via VesselCallWindowProvider (G7-K27)
        $window = $this->marineService->findAvailableBerthWindow(
            $portCall->port_code,
            $berthingTime,
            $unberthingTime,
            $loaM,
            $draftM
        );

        if (! $window || ! $window['is_available']) {
            throw new DomainException("Tidak ada dermaga yang tersedia di {$portCall->port_name} untuk spesifikasi kapal.");
        }

        $booking = $this->marineService->reserveCallWindow(
            $portCall->id,
            $window['berth_code'],
            CarbonImmutable::parse($window['window_start']),
            CarbonImmutable::parse($window['window_end']),
            $window['allocated_length_m'],
            $window['max_draft_m']
        );

        // Jadwalkan pandu masuk (Pilotage Inward)
        $pilot = $this->marineService->requestPilotage(
            $portCall->id,
            'INWARD',
            1350.0,
            'Capt. Harun Al-Rasyid'
        );

        // Jadwalkan kapal tunda jika diperlukan
        $tugs = [];
        if ($requireTugAssistance) {
            $tugs[] = $this->marineService->assignTugboat($portCall->id, 'TB Jayakarta I', 45, 2, 700.0, 'BERTHING');
            if ($loaM > 180.0) {
                // Kapal panjang > 180m membutuhkan minimal 2 kapal tunda
                $tugs[] = $this->marineService->assignTugboat($portCall->id, 'TB Jayakarta II', 50, 2, 750.0, 'BERTHING');
            }
        }

        return [
            'port_call_id' => $portCall->id,
            'berth_booking' => $booking,
            'pilotage_request_id' => $pilot->id,
            'tug_count' => count($tugs),
        ];
    }
}
