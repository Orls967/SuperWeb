<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Enums\SlotMovementType;
use Modules\Aviation\Domain\Enums\SlotRequestStatus;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationOperator;
use Modules\Aviation\Domain\Models\AviationSlot;
use Modules\Aviation\Domain\Models\AviationSlotRequest;
use Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationAllocator;

class AviationSlotService
{
    public function __construct(
        private readonly SlotCoordinationAllocator $allocator
    ) {}

    /**
     * Alokasi batch slot musiman IATA WASG berdasarkan aturan 80/20 dan kuota pendatang baru 50%.
     *
     * @param int $airportId
     * @param string $seasonCode
     * @param int $capacityPerSeason
     * @return array{allocated_count: int, rejected_count: int, historic_count: int, pool_count: int}
     */
    public function processSeasonalAllocation(int $airportId, string $seasonCode, int $capacityPerSeason): array
    {
        $requests = AviationSlotRequest::where('airport_id', $airportId)
            ->where('season_code', $seasonCode)
            ->whereIn('status', [SlotRequestStatus::SUBMITTED->value, SlotRequestStatus::IN_REVIEW->value])
            ->get();

        $rawRequests = $requests->map(fn ($r) => [
            'id' => $r->id,
            'operator_id' => $r->operator_id,
            'is_new_entrant' => (bool) $r->is_new_entrant,
            'requested_time_utc' => $r->requested_time_utc,
            'previous_season_usage_bps' => (int) $r->previous_season_usage_bps,
        ])->toArray();

        $result = $this->allocator->allocate($rawRequests, $capacityPerSeason);

        DB::transaction(function () use ($result, $requests, $airportId, $seasonCode) {
            $allocatedIds = collect($result['allocated_request_ids']);

            foreach ($requests as $req) {
                if ($allocatedIds->contains($req->id)) {
                    $req->transitionTo(SlotRequestStatus::ALLOCATED, 'IATA_WASG_SEASONAL_BATCH');

                    // Terbitkan slot resmi
                    AviationSlot::updateOrCreate(
                        [
                            'airport_id' => $airportId,
                            'operator_id' => $req->operator_id,
                            'season_code' => $seasonCode,
                            'day_of_week' => $req->day_of_week,
                            'allocated_time_utc' => $req->requested_time_utc,
                            'movement_type' => $req->movement_type,
                        ],
                        [
                            'status' => 'confirmed',
                            'is_historic' => $req->previous_season_usage_bps >= 8000,
                            'usage_rate_bps' => $req->previous_season_usage_bps,
                            'notes' => 'Dialokasikan oleh koordinator slot resmi',
                        ]
                    );
                } else {
                    $req->transitionTo(SlotRequestStatus::REJECTED, 'CAPACITY_EXCEEDED');
                }
            }
        });

        return [
            'allocated_count' => count($result['allocated_request_ids']),
            'rejected_count' => count($result['rejected_request_ids']),
            'historic_count' => $result['historic_allocated_count'],
            'pool_count' => $result['pool_allocated_count'],
        ];
    }

    /**
     * Dapatkan daftar slot aktif per bandara dan hari dalam seminggu.
     */
    public function getActiveSlotsForAirport(int $airportId, string $seasonCode, int $dayOfWeek): Collection
    {
        return AviationSlot::with('operator')
            ->where('airport_id', $airportId)
            ->where('season_code', $seasonCode)
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'confirmed')
            ->orderBy('allocated_time_utc')
            ->get();
    }
}

