<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Logistics\Contracts\LogisticsUsageReaderInterface;

class LogisticsUsageReaderService implements LogisticsUsageReaderInterface
{
    public function getPartyIdsForShipper(int $shipperId): array
    {
        return DB::table('lgx_shipper_accounts')
            ->where('shipper_id', $shipperId)
            ->where('is_active', true)
            ->whereNotNull('party_id')
            ->pluck('party_id')
            ->all();
    }

    public function getShipperOutstandingExposure(string $partyId): int
    {
        return (int) DB::table('lgx_shipper_accounts')
            ->where('party_id', $partyId)
            ->sum('current_balance_idr');
    }

    public function getCarrierLegsForParties(array $partyIds): array
    {
        if (empty($partyIds)) {
            return [];
        }

        $carrierIds = DB::table('lgx_carriers')->whereIn('party_id', $partyIds)->pluck('id');
        if ($carrierIds->isEmpty()) {
            return [];
        }

        return DB::table('lgx_shipment_legs')
            ->whereNotNull('cost_accrued_at')
            ->whereIn('carrier_id', $carrierIds)
            ->get(['id', 'carrier_cost_idr'])
            ->map(fn ($leg) => [
                'id' => (int) $leg->id,
                'cost_idr' => (int) $leg->carrier_cost_idr,
                'label' => 'Biaya leg pengiriman #'.$leg->id,
            ])
            ->all();
    }

    public function getPostpaidShipmentsForParties(array $partyIds): array
    {
        if (empty($partyIds)) {
            return [];
        }

        $shipperIds = DB::table('lgx_shipper_accounts')
            ->whereIn('party_id', $partyIds)
            ->pluck('shipper_id');
        if ($shipperIds->isEmpty()) {
            return [];
        }

        return DB::table('lgx_shipments')
            ->whereIn('shipper_id', $shipperIds)
            ->where('payment_terms', 'postpaid')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->get(['id', 'total_amount_idr', 'tracking_number'])
            ->map(fn ($sh) => [
                'id' => (int) $sh->id,
                'amount' => (int) $sh->total_amount_idr,
                'label' => 'Resi pascabayar '.$sh->tracking_number,
            ])
            ->all();
    }
}
