<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Repositories;

use Modules\Hotel\Domain\Models\HtlBanquetOrder;
use Modules\Hotel\Domain\Models\HtlEventProposal;
use Modules\Hotel\Domain\Models\HtlExhibitionBoothReservation;
use Modules\Hotel\Domain\Models\HtlMiceLead;
use Modules\Hotel\Domain\Models\MiceContract;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;

final class HotelMiceReportingRepository
{
    /**
     * @return array<string, mixed>
     */
    public function getExecutiveMiceSummary(): array
    {
        $totalLeads = HtlMiceLead::count();
        $qualifiedLeads = HtlMiceLead::where('status', 'QUALIFIED')->count();
        $leadConversionRate = $totalLeads > 0 ? round(($qualifiedLeads / $totalLeads) * 100, 2) : 0.0;

        $proposalsCount = HtlEventProposal::count();
        $approvedProposals = HtlEventProposal::where('status', 'APPROVED')->count();
        $proposalWinRate = $proposalsCount > 0 ? round(($approvedProposals / $proposalsCount) * 100, 2) : 0.0;

        $totalContractValue = (float) MiceContract::sum('contract_total_value_idr');
        $activeContractsCount = MiceContract::whereIn('status', ['CONFIRMED', 'QUOTED'])->count();

        $totalAllocatedRooms = (int) RoomBlockAllotment::sum('allotted_rooms_count');
        $totalPickedUpRooms = (int) RoomBlockAllotment::sum('confirmed_rooms_count');
        $overallPickupRate = $totalAllocatedRooms > 0 ? round(($totalPickedUpRooms / $totalAllocatedRooms) * 100, 2) : 0.0;

        $totalBoothsReserved = HtlExhibitionBoothReservation::count();
        $totalBoothRevenue = (float) HtlExhibitionBoothReservation::sum('base_rental_idr');

        $totalBanquetPax = (int) HtlBanquetOrder::sum('guaranteed_pax');

        return [
            'total_leads' => $totalLeads,
            'qualified_leads' => $qualifiedLeads,
            'lead_conversion_rate' => $leadConversionRate,
            'total_proposals' => $proposalsCount,
            'approved_proposals' => $approvedProposals,
            'proposal_win_rate' => $proposalWinRate,
            'total_contract_value' => $totalContractValue,
            'active_contracts_count' => $activeContractsCount,
            'total_allocated_rooms' => $totalAllocatedRooms,
            'total_picked_up_rooms' => $totalPickedUpRooms,
            'overall_pickup_rate' => $overallPickupRate,
            'total_booths_reserved' => $totalBoothsReserved,
            'total_booth_revenue' => $totalBoothRevenue,
            'total_banquet_pax' => $totalBanquetPax,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyRevenuePipeline(): array
    {
        $results = [];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        foreach ($months as $idx => $m) {
            $monthNum = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $contractRevenue = (float) MiceContract::whereRaw("strftime('%m', created_at) = ?", [$monthNum])->sum('contract_total_value_idr');
            $boothRev = (float) HtlExhibitionBoothReservation::whereRaw("strftime('%m', created_at) = ?", [$monthNum])->sum('base_rental_idr');

            $results[] = [
                'month' => $m,
                'contract_revenue' => $contractRevenue,
                'booth_revenue' => $boothRev,
                'total_pipeline' => $contractRevenue + $boothRev,
            ];
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRoomBlockPickupPace(): array
    {
        $allotments = RoomBlockAllotment::with('contract')->get();
        $paceData = [];

        foreach ($allotments as $allotment) {
            $allocated = (int) $allotment->allotted_rooms_count;
            $picked = (int) $allotment->confirmed_rooms_count;
            $released = (int) $allotment->released_rooms_count;
            $pickupPct = $allocated > 0 ? round(($picked / $allocated) * 100, 2) : 0.0;

            $paceData[] = [
                'contract_id' => $allotment->mice_contract_id,
                'contract_number' => $allotment->contract?->event_code ?? 'N/A',
                'block_code' => $allotment->block_code,
                'allocated' => $allocated,
                'picked_up' => $picked,
                'released' => $released,
                'pickup_percent' => $pickupPct,
                'status' => $allotment->status,
            ];
        }

        return $paceData;
    }
}
