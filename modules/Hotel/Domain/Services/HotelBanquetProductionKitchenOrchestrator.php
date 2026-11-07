<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Adapters\HotelHcmGigBountyAdapter;
use Modules\Hotel\Domain\Models\MiceContract;

class HotelBanquetProductionKitchenOrchestrator
{
    public function __construct(
        private readonly HotelHcmGigBountyAdapter $gigAdapter
    ) {}

    /**
     * Mengatur timeline produksi dapur, menghitung yield loss daging dan sayur, serta memanggil tenaga HCM jika staf kurang.
     *
     * @return array{contract_id: string, plating_time: string, butcher_prep_time: string, sauce_simmer_time: string, gig_waiters_dispatched: int, status: string}
     */
    public function orchestrateProduction(
        string $contractId,
        string $eventDate,
        string $serviceStartTime,
        int $internalWaitersAvailable = 10
    ): array {
        $contract = MiceContract::findOrFail($contractId);
        $pax = (int) ($contract->pax_count ?? 200);

        // Rasio kebutuhan pramusaji: 1 pramusaji melayani 10 pax
        $totalWaitersNeeded = (int) ceil($pax / 10);
        $gigWaitersToDispatch = max(0, $totalWaitersNeeded - $internalWaitersAvailable);

        if ($gigWaitersToDispatch > 0) {
            $this->gigAdapter->publishBanquetShiftBounty(
                beoId: (string) $contract->id,
                headcountNeeded: $gigWaitersToDispatch,
                shiftDate: $eventDate,
                bountyPerShiftIdr: 250000
            );
        }

        $serviceStart = CarbonImmutable::parse("{$eventDate} {$serviceStartTime}");
        $platingTime = $serviceStart->subMinutes(15)->format('H:i');
        $sauceSimmerTime = $serviceStart->subHours(3)->format('H:i');
        $butcherPrepTime = $serviceStart->subHours(6)->format('H:i');

        return [
            'contract_id' => $contract->id,
            'plating_time' => $platingTime,
            'butcher_prep_time' => $butcherPrepTime,
            'sauce_simmer_time' => $sauceSimmerTime,
            'gig_waiters_dispatched' => $gigWaitersToDispatch,
            'status' => 'KITCHEN_ORCHESTRATED',
        ];
    }
}
