<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hotel\Domain\Models\BanquetProductionSheet;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;
use RuntimeException;

class MiceAndWeddingSalesService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 114.1 Create MICE / Wedding Contract
     */
    public function createMiceContract(
        HotelProperty $property,
        string $eventType,
        string $clientName,
        string $eventDate,
        int $totalContractValueIdr
    ): MiceContract {
        return MiceContract::create([
            'id' => (string) Str::uuid(),
            'event_code' => 'MICE-'.strtoupper(bin2hex(random_bytes(4))),
            'property_id' => $property->id,
            'event_type' => $eventType,
            'client_name' => $clientName,
            'event_date' => $eventDate,
            'contract_total_value_idr' => $totalContractValueIdr,
            'actual_banquet_cost_idr' => 0,
            'current_milestone_index' => 1,
            'status' => 'CONFIRMED',
        ]);
    }

    /**
     * 114.2 Create Room Block Allotment
     */
    public function createRoomBlock(
        MiceContract $contract,
        string $checkInDate,
        int $allottedRooms,
        int $cutoffDays = 30
    ): RoomBlockAllotment {
        $releaseDeadline = date('Y-m-d', strtotime("{$checkInDate} -{$cutoffDays} days"));

        return RoomBlockAllotment::create([
            'id' => (string) Str::uuid(),
            'block_code' => 'BLK-'.strtoupper(bin2hex(random_bytes(4))),
            'mice_contract_id' => $contract->id,
            'check_in_date' => $checkInDate,
            'release_deadline_date' => $releaseDeadline,
            'allotted_rooms_count' => $allottedRooms,
            'confirmed_rooms_count' => 0,
            'released_rooms_count' => 0,
            'status' => 'HELD',
        ]);
    }

    /**
     * 114.2 & 114.6 (a) Auto-release unconfirmed room blocks on H-30 cutoff date
     */
    public function releaseUnconfirmedRooms(RoomBlockAllotment $block, string $currentDate): int
    {
        if (strtotime($currentDate) < strtotime($block->release_deadline_date)) {
            return 0; // Cutoff date not reached yet
        }

        $unconfirmed = $block->allotted_rooms_count - $block->confirmed_rooms_count;
        if ($unconfirmed <= 0) {
            return 0;
        }

        $block->update([
            'released_rooms_count' => $unconfirmed,
            'status' => 'RELEASED',
        ]);

        return $unconfirmed;
    }

    /**
     * 114.3 Create Banquet Production Sheet & Post Costs
     */
    public function recordBanquetProduction(
        MiceContract $contract,
        string $menuPackage,
        int $paxCount,
        int $fnbCostIdr,
        int $avCostIdr,
        int $decorCostIdr
    ): BanquetProductionSheet {
        $totalCostIdr = $fnbCostIdr + $avCostIdr + $decorCostIdr;

        return DB::transaction(function () use (
            $contract,
            $menuPackage,
            $paxCount,
            $fnbCostIdr,
            $avCostIdr,
            $decorCostIdr,
            $totalCostIdr
        ) {
            $sheet = BanquetProductionSheet::create([
                'id' => (string) Str::uuid(),
                'mice_contract_id' => $contract->id,
                'menu_package_name' => $menuPackage,
                'pax_count' => $paxCount,
                'fnb_materials_cost_idr' => $fnbCostIdr,
                'av_equipment_vendor_cost_idr' => $avCostIdr,
                'decor_florist_vendor_cost_idr' => $decorCostIdr,
                'total_production_cost_idr' => $totalCostIdr,
            ]);

            $contract->update(['actual_banquet_cost_idr' => $totalCostIdr]);

            // Post banquet cost in ledger
            $this->ledgerService->post(new PostingDTO(
                type: 'MICE_BANQUET_PRODUCTION_COST',
                description: "Actual banquet production cost for event {$contract->event_code}",
                idempotencyKey: "MICE-BOM-{$sheet->id}",
                entries: [
                    PostingEntryDTO::forCode('htl:banquet_production_cost:IDR', 'IDR', $totalCostIdr),
                    PostingEntryDTO::forCode('htl:banquet_vendor_payable:IDR', 'IDR', -$totalCostIdr),
                ],
                referenceType: 'BANQUET_PRODUCTION_SHEET',
                referenceId: $sheet->id,
            ));

            return $sheet;
        });
    }

    /**
     * 114.4 & 114.6 (d) Advance Wedding Payment Milestones (20% -> 30% -> 40% -> 10%)
     */
    public function advanceWeddingMilestone(MiceContract $contract, int $expectedIndex): int
    {
        $milestonePercents = [
            1 => 20, // Milestone 1: 20% booking deposit
            2 => 30, // Milestone 2: 30% H-90 payment
            3 => 40, // Milestone 3: 40% H-30 payment
            4 => 10, // Milestone 4: 10% post-event settlement
        ];

        if ($expectedIndex !== $contract->current_milestone_index) {
            throw new RuntimeException("Invalid wedding payment milestone: expected {$contract->current_milestone_index} but got {$expectedIndex}");
        }

        $percent = $milestonePercents[$expectedIndex];
        $milestoneAmountIdr = (int) round(($contract->contract_total_value_idr * $percent) / 100);

        DB::transaction(function () use ($contract, $expectedIndex, $milestoneAmountIdr) {
            $this->ledgerService->post(new PostingDTO(
                type: 'WEDDING_MILESTONE_PAYMENT',
                description: "Milestone {$expectedIndex} payment for wedding event {$contract->event_code}",
                idempotencyKey: "WEDDING-MS-{$contract->event_code}-{$expectedIndex}",
                entries: [
                    PostingEntryDTO::forCode('htl:wedding_receivable:IDR', 'IDR', $milestoneAmountIdr),
                    PostingEntryDTO::forCode('htl:wedding_revenue:IDR', 'IDR', -$milestoneAmountIdr),
                ],
                referenceType: 'MICE_CONTRACT',
                referenceId: $contract->id,
            ));

            $nextIndex = ($expectedIndex < 4) ? $expectedIndex + 1 : 4;
            $contract->update([
                'current_milestone_index' => $nextIndex,
                'status' => ($expectedIndex === 4) ? 'COMPLETED' : 'CONFIRMED',
            ]);
        });

        return $milestoneAmountIdr;
    }
}
