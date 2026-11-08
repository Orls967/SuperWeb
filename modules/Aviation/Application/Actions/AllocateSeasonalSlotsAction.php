<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Enums\SlotRequestStatus;
use Modules\Aviation\Domain\Models\AviationAirport;
use Modules\Aviation\Domain\Models\AviationSlot;
use Modules\Aviation\Domain\Models\AviationSlotRequest;
use Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationAllocator;
use Modules\Shared\Domain\Algorithms\Aviation\SlotCoordinationResult;

class AllocateSeasonalSlotsAction
{
    public function __construct(
        private readonly SlotCoordinationAllocator $allocator
    ) {
    }

    public function execute(int $airportId, string $seasonCode): SlotCoordinationResult
    {
        return DB::transaction(function () use ($airportId, $seasonCode) {
            $airport = AviationAirport::findOrFail($airportId);

            // 1. Ambil slot historis
            $existingSlots = AviationSlot::where('airport_id', $airportId)
                ->where('season_code', $seasonCode)
                ->with('operator')
                ->get();

            $historicalData = [];
            foreach ($existingSlots as $slot) {
                $historicalData[] = [
                    'operator' => $slot->operator->code,
                    'held' => 1,
                    'usage_bps' => $slot->usage_rate_bps,
                ];
            }

            // Agregasi held per operator
            $histAggregated = [];
            foreach ($historicalData as $h) {
                $op = $h['operator'];
                if (! isset($histAggregated[$op])) {
                    $histAggregated[$op] = ['operator' => $op, 'held' => 0, 'usage_bps' => $h['usage_bps']];
                }
                $histAggregated[$op]['held'] += $h['held'];
            }

            // 2. Ambil permintaan slot yang berstatus submitted
            $requests = AviationSlotRequest::where('airport_id', $airportId)
                ->where('season_code', $seasonCode)
                ->where('status', SlotRequestStatus::SUBMITTED->value)
                ->with('operator')
                ->orderBy('requested_time')
                ->get();

            $requestInputs = [];
            foreach ($requests as $r) {
                $requestInputs[] = [
                    'operator' => $r->operator->code,
                    'requested' => 1,
                    'is_new_entrant' => (bool) $r->is_new_entrant,
                    'request_time' => (int) $r->requested_time,
                ];
            }

            // Jalankan A1
            $capacity = $airport->hourly_capacity;
            $result = $this->allocator->allocate($capacity, array_values($histAggregated), $requestInputs);

            // Perbarui status permintaan
            foreach ($requests as $r) {
                $opCode = $r->operator->code;
                if (($result->allocated[$opCode] ?? 0) > 0) {
                    $r->transitionTo(SlotRequestStatus::ALLOCATED, 'A1_ALLOCATOR');
                    // Buat slot fisik baru
                    AviationSlot::create([
                        'airport_id' => $airportId,
                        'operator_id' => $r->operator_id,
                        'season_code' => $seasonCode,
                        'day_of_week' => 1,
                        'time_window_start' => '08:00',
                        'time_window_end' => '09:00',
                        'is_historic' => false,
                        'usage_rate_bps' => 10000,
                        'status' => 'confirmed',
                    ]);
                } else {
                    $r->transitionTo(SlotRequestStatus::WAITLISTED, 'A1_CAPACITY_LIMIT');
                }
            }

            return $result;
        });
    }
}

