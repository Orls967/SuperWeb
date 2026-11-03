<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use InvalidArgumentException;
use Modules\Port\Domain\Models\Berth;
use Modules\Port\Domain\Models\VesselCall;
use Modules\Shared\Domain\Algorithms\Scheduling\BerthAllocation;

class AllocateBerthAction
{
    public function __construct(
        private readonly BerthAllocation $berthAllocation = new BerthAllocation
    ) {}

    /**
     * Menjadwalkan vessel calls ke tambatan diskrit.
     *
     * @param  list<int>  $vesselCallIds
     * @return array<string, mixed>
     */
    public function execute(array $vesselCallIds): array
    {
        $berths = Berth::orderBy('id')->get();
        if ($berths->isEmpty()) {
            throw new InvalidArgumentException('Tidak ada tambatan yang tersedia.');
        }

        $vesselCalls = VesselCall::whereIn('id', $vesselCallIds)
            ->orderBy('id')
            ->get();

        if ($vesselCalls->isEmpty()) {
            throw new InvalidArgumentException('Tidak ada vessel call yang dipilih.');
        }

        $berthMap = [];
        $index = 1;
        foreach ($berths as $berth) {
            $berthMap[$index] = $berth;
            $index++;
        }
        $numBerths = count($berths);

        $vesselsInput = [];
        foreach ($vesselCalls as $call) {
            // Konversi ETA menjadi jam relatif dari waktu sekarang atau epoch
            $arrivalHour = $call->eta ? (int) $call->eta->format('H') : 1;
            if ($arrivalHour < 1) {
                $arrivalHour = 1;
            }

            $vesselsInput[] = [
                'id' => $call->id,
                'arrival' => $arrivalHour,
                'duration' => max(1, (int) ceil($call->duration_hours / 4)), // slot diskrit
                'weight' => max(1, (int) $call->weight),
            ];
        }

        $allocationResult = $this->berthAllocation->allocate($numBerths, $vesselsInput);

        // Update database vessel calls
        foreach ($allocationResult['allocations'] as $callId => $alloc) {
            $berthModel = $berthMap[$alloc['berth']] ?? $berths->first();
            $call = $vesselCalls->firstWhere('id', $callId);
            if ($call) {
                $call->update([
                    'berth_id' => $berthModel->id,
                    'status' => 'SCHEDULED',
                    'schedule_certificate' => [
                        'berth_index' => $alloc['berth'],
                        'start_time' => $alloc['start'],
                        'end_time' => $alloc['end'],
                        'wait_time' => $alloc['wait_time'],
                        'cost' => $alloc['cost'],
                    ],
                ]);
            }
        }

        return $allocationResult;
    }
}
