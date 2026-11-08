<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;

final class HotelMiceContractAddendumManagerService
{
    /**
     * @param array{
     *     addendum_type: string,
     *     additional_rooms?: int,
     *     additional_pax?: int,
     *     additional_cost: float,
     *     reason: string
     * } $payload
     * @return array<string, mixed>
     */
    public function issueAddendum(string $contractId, array $payload): array
    {
        $contract = MiceContract::findOrFail($contractId);

        DB::beginTransaction();
        try {
            $addendumType = (string) ($payload['addendum_type'] ?? 'scope_adjustment');
            $additionalCost = (float) ($payload['additional_cost'] ?? 0.0);
            $reason = (string) ($payload['reason'] ?? 'Client requested scope adjustment');

            $oldTotal = (float) ($contract->contract_total_value_idr ?? 0);
            $newTotal = $oldTotal + $additionalCost;

            if ($addendumType === 'additional_rooms' && isset($payload['additional_rooms'])) {
                $rooms = (int) $payload['additional_rooms'];
                $allotment = RoomBlockAllotment::where('mice_contract_id', $contract->id)->first();
                if ($allotment) {
                    $allotment->update([
                        'allotted_rooms_count' => (int) $allotment->allotted_rooms_count + $rooms,
                    ]);
                }
            } elseif ($addendumType === 'additional_pax' && isset($payload['additional_pax'])) {
                $pax = (int) $payload['additional_pax'];
                $beo = HtlBanquetOrder::where('mice_contract_id', $contract->id)->first();
                if ($beo) {
                    $beo->update([
                        'guaranteed_pax' => (int) $beo->guaranteed_pax + $pax,
                        'prepared_pax' => (int) ceil(((int) $beo->guaranteed_pax + $pax) * 1.05),
                    ]);
                }
            }

            $contract->update([
                'contract_total_value_idr' => (int) $newTotal,
            ]);

            $addendumNumber = 'ADD-'.($contract->event_code ?? 'MICE').'-'.strtoupper(Str::random(4));

            DB::commit();

            Log::info('MICE Contract Addendum issued', [
                'contract_id' => $contract->id,
                'addendum_number' => $addendumNumber,
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
                'additional_cost' => $additionalCost,
            ]);

            return [
                'addendum_number' => $addendumNumber,
                'contract_id' => $contract->id,
                'contract_number' => $contract->event_code,
                'previous_total' => $oldTotal,
                'new_total' => $newTotal,
                'delta_amount' => $additionalCost,
                'addendum_type' => $addendumType,
                'reason' => $reason,
                'issued_at' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to issue MICE Addendum: '.$e->getMessage());
            throw $e;
        }
    }
}
