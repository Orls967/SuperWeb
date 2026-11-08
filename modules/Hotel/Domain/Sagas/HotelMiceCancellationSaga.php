<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Sagas;

use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Models\HtlExhibitionBoothReservation;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;

final class HotelMiceCancellationSaga
{
    /**
     * @return array<string, mixed>
     */
    public function cancel(string $contractId, string $cancellationReason = 'Client requested cancellation'): array
    {
        $contract = MiceContract::findOrFail($contractId);

        DB::beginTransaction();
        try {
            // 1. Calculate penalty based on notice period
            $eventDate = new DateTimeImmutable($contract->event_date ?? date('Y-m-d'));
            $now = new DateTimeImmutable;
            $daysNotice = (int) $now->diff($eventDate)->format('%r%a');

            $contractVal = (float) ($contract->contract_total_value_idr ?? 0);
            $penaltyAmount = 0.0;
            $refundAmount = $contractVal;

            if ($daysNotice < 30) {
                // < 30 days notice: 100% cancellation penalty
                $penaltyAmount = $contractVal;
                $refundAmount = 0.0;
            } elseif ($daysNotice < 60) {
                // 30-60 days: 50% cancellation penalty
                $penaltyAmount = round($contractVal * 0.50, 2);
                $refundAmount = round($contractVal * 0.50, 2);
            } else {
                // > 60 days: 15% administrative penalty
                $penaltyAmount = round($contractVal * 0.15, 2);
                $refundAmount = round($contractVal * 0.85, 2);
            }

            // 2. Release room block allotments
            $allotments = RoomBlockAllotment::where('mice_contract_id', $contract->id)->get();
            $totalRoomsReleased = 0;
            foreach ($allotments as $allotment) {
                $unpicked = max(0, (int) $allotment->allotted_rooms_count - (int) $allotment->confirmed_rooms_count);
                $allotment->update([
                    'released_rooms_count' => (int) $allotment->released_rooms_count + $unpicked,
                    'status' => 'RELEASED',
                ]);
                $totalRoomsReleased += $unpicked;
            }

            // 3. Cancel Banquet BEOs
            HtlBanquetOrder::where('mice_contract_id', $contract->id)->update([
                'status' => 'CANCELLED',
            ]);

            // 4. Release exhibition booths
            HtlExhibitionBoothReservation::where('mice_contract_id', $contract->id)->update([
                'status' => 'CANCELLED',
            ]);

            // 5. Update contract status
            $contract->update([
                'status' => 'CANCELLED',
            ]);

            DB::commit();

            Log::info('HotelMiceCancellationSaga completed', [
                'contract_id' => $contract->id,
                'days_notice' => $daysNotice,
                'penalty_amount' => $penaltyAmount,
                'refund_amount' => $refundAmount,
                'rooms_released' => $totalRoomsReleased,
            ]);

            return [
                'status' => 'cancelled',
                'contract_id' => $contract->id,
                'days_notice' => $daysNotice,
                'penalty_amount' => $penaltyAmount,
                'refund_amount' => $refundAmount,
                'rooms_released' => $totalRoomsReleased,
                'reason' => $cancellationReason,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Failed to cancel MICE Contract {$contractId}: ".$e->getMessage());
            throw $e;
        }
    }
}
