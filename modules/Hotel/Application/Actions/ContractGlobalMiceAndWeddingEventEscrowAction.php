<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Hotel\Domain\Models\BanquetProductionSheet;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;
use Modules\Shared\Application\BaseAction;

class ContractGlobalMiceAndWeddingEventEscrowAction extends BaseAction
{
    /**
     * Create MICE/Wedding contract, manage H-30 room block release, and record banquet production BOM margin.
     *
     * @return array{contract: MiceContract, room_block: RoomBlockAllotment, released_rooms: int, banquet_sheet: ?BanquetProductionSheet, event_gross_margin_idr: int}
     */
    public function handle(
        string $propertyId,
        string $eventType,
        string $clientName,
        string $eventDate,
        int $contractTotalValueIdr,
        int $allottedRooms = 100,
        string $currentDate = '',
        int $fnbMaterialsCostIdr = 0,
        int $avVendorCostIdr = 0,
        int $decorFloristCostIdr = 0,
        int $paxCount = 200
    ): array {
        return DB::transaction(function () use (
            $propertyId,
            $eventType,
            $clientName,
            $eventDate,
            $contractTotalValueIdr,
            $allottedRooms,
            $currentDate,
            $fnbMaterialsCostIdr,
            $avVendorCostIdr,
            $decorFloristCostIdr,
            $paxCount
        ) {
            $property = HotelProperty::lockForUpdate()->findOrFail($propertyId);

            $eventCount = MiceContract::where('property_id', $property->id)->count() + 1;
            $eventCode = sprintf('MICE-%s-%04d', $property->property_code, $eventCount);

            // 114.1 Create MICE / Wedding Contract
            $contract = MiceContract::create([
                'id' => (string) Str::uuid(),
                'event_code' => $eventCode,
                'property_id' => $property->id,
                'event_type' => strtoupper($eventType),
                'client_name' => $clientName,
                'event_date' => $eventDate,
                'contract_total_value_idr' => $contractTotalValueIdr,
                'actual_banquet_cost_idr' => 0,
                'current_milestone_index' => 1,
                'status' => 'CONFIRMED',
            ]);

            // 114.2 Room Block Allotment with H-30 cutoff date
            $releaseDeadline = date('Y-m-d', strtotime("{$eventDate} -30 days"));
            $block = RoomBlockAllotment::create([
                'id' => (string) Str::uuid(),
                'block_code' => 'BLK-'.strtoupper(bin2hex(random_bytes(4))),
                'mice_contract_id' => $contract->id,
                'check_in_date' => $eventDate,
                'release_deadline_date' => $releaseDeadline,
                'allotted_rooms_count' => $allottedRooms,
                'confirmed_rooms_count' => 0,
                'released_rooms_count' => 0,
                'status' => 'HELD',
            ]);

            $releasedRooms = 0;
            $evalDate = empty($currentDate) ? date('Y-m-d') : $currentDate;
            if (strtotime($evalDate) >= strtotime($releaseDeadline)) {
                $releasedRooms = $allottedRooms;
                $block->update([
                    'released_rooms_count' => $releasedRooms,
                    'status' => 'RELEASED',
                ]);
            }

            // 114.3 Banquet Production Sheet (BEO)
            $banquetSheet = null;
            $totalProductionCostIdr = $fnbMaterialsCostIdr + $avVendorCostIdr + $decorFloristCostIdr;

            if ($totalProductionCostIdr > 0) {
                $banquetSheet = BanquetProductionSheet::create([
                    'id' => (string) Str::uuid(),
                    'mice_contract_id' => $contract->id,
                    'menu_package_name' => "Royal Banquet {$eventType} Package",
                    'pax_count' => $paxCount,
                    'fnb_materials_cost_idr' => $fnbMaterialsCostIdr,
                    'av_equipment_vendor_cost_idr' => $avVendorCostIdr,
                    'decor_florist_vendor_cost_idr' => $decorFloristCostIdr,
                    'total_production_cost_idr' => $totalProductionCostIdr,
                ]);

                $contract->update(['actual_banquet_cost_idr' => $totalProductionCostIdr]);
            }

            $grossMarginIdr = $contractTotalValueIdr - $totalProductionCostIdr;

            $this->audit(
                action: 'hotel.mice_contract.executed',
                auditable: $contract,
                oldValues: null,
                newValues: [
                    'event_code' => $eventCode,
                    'client_name' => $clientName,
                    'total_contract_value_idr' => $contractTotalValueIdr,
                    'allotted_rooms' => $allottedRooms,
                    'released_rooms' => $releasedRooms,
                    'gross_margin_idr' => $grossMarginIdr,
                ],
                correlationId: "htl_mice_{$eventCode}",
                impactType: 'mice_wedding_contract'
            );

            return [
                'contract' => $contract,
                'room_block' => $block,
                'released_rooms' => $releasedRooms,
                'banquet_sheet' => $banquetSheet,
                'event_gross_margin_idr' => $grossMarginIdr,
            ];
        });
    }
}
