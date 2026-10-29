<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Hotel\Domain\Models\HotelDailyRevenueSnapshot;

class HotelReportService
{
    /**
     * Mengambil laporan harian pendapatan dan operasional hotel terverifikasi ledger.
     *
     * @return array{
     *     property_id: string,
     *     business_date: string,
     *     total_rooms: int,
     *     occupied_rooms: int,
     *     out_of_order_rooms: int,
     *     available_rooms: int,
     *     occupancy_rate_bps: int,
     *     occupancy_rate_percent: float,
     *     total_room_revenue_idr: int,
     *     total_fnb_revenue_idr: int,
     *     total_service_charge_idr: int,
     *     total_tax_pbjt_idr: int,
     *     adr_idr: int,
     *     revpar_idr: int,
     *     ledger_room_revenue_idr: int,
     *     is_reconciled: bool
     * }
     */
    public function getDailyReport(string $propertyId, string $businessDate): array
    {
        $dateStr = Carbon::parse($businessDate)->toDateString();
        $snapshot = HotelDailyRevenueSnapshot::where('property_id', $propertyId)
            ->whereDate('business_date', $dateStr)
            ->first();

        if (! $snapshot) {
            return [
                'property_id' => $propertyId,
                'business_date' => $dateStr,
                'total_rooms' => 0,
                'occupied_rooms' => 0,
                'out_of_order_rooms' => 0,
                'available_rooms' => 0,
                'occupancy_rate_bps' => 0,
                'occupancy_rate_percent' => 0.0,
                'total_room_revenue_idr' => 0,
                'total_fnb_revenue_idr' => 0,
                'total_service_charge_idr' => 0,
                'total_tax_pbjt_idr' => 0,
                'adr_idr' => 0,
                'revpar_idr' => 0,
                'ledger_room_revenue_idr' => 0,
                'is_reconciled' => true,
            ];
        }

        // Ambil mutasi kredit riil pada akun htl:room_revenue:IDR dari Banking Ledger
        $ledgerRoomRev = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'htl:room_revenue:IDR'))
            ->whereDate('created_at', $dateStr)
            ->sum('amount');

        // Jika tidak tercatat di created_at hari ini, bandingkan total ledger mutasi positif
        if ($ledgerRoomRev === 0) {
            $ledgerRoomRev = (int) LedgerEntry::whereHas('account', fn ($q) => $q->where('code', 'htl:room_revenue:IDR'))
                ->where('amount', '>', 0)
                ->sum('amount');
        }

        $availableRooms = max(0, $snapshot->total_rooms - $snapshot->out_of_order_rooms);

        return [
            'property_id' => $propertyId,
            'business_date' => $dateStr,
            'total_rooms' => $snapshot->total_rooms,
            'occupied_rooms' => $snapshot->occupied_rooms,
            'out_of_order_rooms' => $snapshot->out_of_order_rooms,
            'available_rooms' => $availableRooms,
            'occupancy_rate_bps' => $snapshot->occupancy_rate_bps,
            'occupancy_rate_percent' => round($snapshot->occupancy_rate_bps / 100, 2),
            'total_room_revenue_idr' => $snapshot->total_room_revenue_idr,
            'total_fnb_revenue_idr' => $snapshot->total_fnb_revenue_idr,
            'total_service_charge_idr' => $snapshot->total_service_charge_idr,
            'total_tax_pbjt_idr' => $snapshot->total_tax_pbjt_idr,
            'adr_idr' => $snapshot->adr_idr,
            'revpar_idr' => $snapshot->revpar_idr,
            'ledger_room_revenue_idr' => $ledgerRoomRev,
            'is_reconciled' => $snapshot->total_room_revenue_idr <= $ledgerRoomRev,
        ];
    }

    /**
     * Mengambil riwayat snapshot pendapatan dalam rentang tanggal.
     */
    public function getDateRangeReport(string $propertyId, string $startDate, string $endDate): Collection
    {
        return HotelDailyRevenueSnapshot::where('property_id', $propertyId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy('business_date')
            ->get();
    }
}
