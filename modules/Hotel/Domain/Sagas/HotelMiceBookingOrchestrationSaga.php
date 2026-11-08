<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Sagas;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Models\HtlExhibitionBoothReservation;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;

final class HotelMiceBookingOrchestrationSaga
{
    /**
     * @var array<int, string>
     */
    private array $compensationStack = [];

    /**
     * @param array{
     *     contract_id: string,
     *     rooms_count: int,
     *     catering_pax: int,
     *     booth_numbers: array<int, string>,
     *     credit_limit?: float
     * } $context
     * @return array<string, mixed>
     */
    public function execute(array $context): array
    {
        $this->compensationStack = [];
        $contractId = (string) $context['contract_id'];
        $contract = MiceContract::find($contractId);
        if (! $contract) {
            throw new Exception("MICE Contract not found: {$contractId}");
        }

        DB::beginTransaction();
        try {
            // STEP 1: Hold Room Block Allotment
            $allotment = $this->stepHoldRoomBlock($contract, (int) $context['rooms_count']);
            $this->compensationStack[] = 'release_room_block';

            // STEP 2: Lock Banquet Ballroom & Catering
            $banquetOrder = $this->stepLockBanquetCatering($contract, (int) $context['catering_pax']);
            $this->compensationStack[] = 'cancel_banquet_order';

            // STEP 3: Reserve Exhibition Booths
            $booths = $this->stepReserveExhibitionBooths($contract, (array) ($context['booth_numbers'] ?? []));
            $this->compensationStack[] = 'release_exhibition_booths';

            // STEP 4: Provision MICE Master Folio with Credit Limit
            $folio = $this->stepProvisionMasterFolio($contract, (float) ($context['credit_limit'] ?? 50000000.0));
            $this->compensationStack[] = 'close_master_folio';

            DB::commit();

            Log::info('HotelMiceBookingOrchestrationSaga completed successfully', [
                'contract_id' => $contractId,
                'allotment_id' => $allotment?->id,
                'banquet_id' => $banquetOrder?->id,
                'folio_id' => $folio['folio_id'] ?? null,
            ]);

            return [
                'status' => 'success',
                'contract_id' => $contractId,
                'allotment_id' => $allotment?->id,
                'banquet_order_id' => $banquetOrder?->id,
                'booth_count' => count($booths),
                'master_folio_id' => $folio['folio_id'] ?? null,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('HotelMiceBookingOrchestrationSaga failed. Initiating compensation.', [
                'error' => $e->getMessage(),
                'contract_id' => $contractId,
            ]);
            $this->compensate($contract);
            throw $e;
        }
    }

    private function stepHoldRoomBlock(MiceContract $contract, int $roomsCount): ?RoomBlockAllotment
    {
        if ($roomsCount <= 0) {
            return null;
        }

        return RoomBlockAllotment::create([
            'id' => (string) Str::uuid(),
            'block_code' => 'BLK-'.strtoupper(Str::random(8)),
            'mice_contract_id' => $contract->id,
            'check_in_date' => $contract->event_date ?? date('Y-m-d', strtotime('+30 days')),
            'release_deadline_date' => date('Y-m-d', strtotime('+15 days')),
            'allotted_rooms_count' => $roomsCount,
            'confirmed_rooms_count' => 0,
            'released_rooms_count' => 0,
            'status' => 'HELD',
        ]);
    }

    private function stepLockBanquetCatering(MiceContract $contract, int $pax): ?HtlBanquetOrder
    {
        if ($pax <= 0) {
            return null;
        }

        $prepared = (int) ceil($pax * 1.05);

        return HtlBanquetOrder::create([
            'id' => (string) Str::uuid(),
            'beo_code' => 'BEO-'.strtoupper(Str::random(8)),
            'mice_contract_id' => $contract->id,
            'title' => 'Gala Dinner Plenary Session',
            'meal_period' => 'DINNER',
            'guaranteed_pax' => $pax,
            'prepared_pax' => $prepared,
            'event_date' => $contract->event_date ?? date('Y-m-d', strtotime('+30 days')),
            'status' => 'CONFIRMED',
        ]);
    }

    /**
     * @param  array<int, string>  $boothNumbers
     * @return array<int, HtlExhibitionBoothReservation>
     */
    private function stepReserveExhibitionBooths(MiceContract $contract, array $boothNumbers): array
    {
        $reserved = [];
        foreach ($boothNumbers as $number) {
            $reserved[] = HtlExhibitionBoothReservation::create([
                'id' => (string) Str::uuid(),
                'reservation_code' => 'RES-BTH-'.strtoupper(Str::random(8)),
                'mice_contract_id' => $contract->id,
                'booth_number' => $number,
                'booth_type' => 'SHELL_3X3',
                'base_rental_idr' => 15000000,
                'utility_charge_idr' => 1500000,
                'status' => 'CONFIRMED',
            ]);
        }

        return $reserved;
    }

    /**
     * @return array<string, mixed>
     */
    private function stepProvisionMasterFolio(MiceContract $contract, float $creditLimit): array
    {
        return [
            'folio_id' => 'MF-'.strtoupper(Str::random(8)),
            'contract_id' => $contract->id,
            'credit_limit' => $creditLimit,
            'status' => 'active',
        ];
    }

    private function compensate(MiceContract $contract): void
    {
        while (! empty($this->compensationStack)) {
            $step = array_pop($this->compensationStack);
            try {
                match ($step) {
                    'close_master_folio' => Log::info("Compensated: Closed master folio for {$contract->id}"),
                    'release_exhibition_booths' => HtlExhibitionBoothReservation::where('mice_contract_id', $contract->id)->delete(),
                    'cancel_banquet_order' => HtlBanquetOrder::where('mice_contract_id', $contract->id)->delete(),
                    'release_room_block' => RoomBlockAllotment::where('mice_contract_id', $contract->id)->delete(),
                    default => null,
                };
            } catch (Exception $ce) {
                Log::critical("Compensation failure during step {$step}: ".$ce->getMessage());
            }
        }
    }
}
