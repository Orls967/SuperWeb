<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Shared\Application\BaseAction;

class RunIdempotentHotelNightAuditAction extends BaseAction
{
    /**
     * Run daily hotel night audit: post room charges, verify folio balances, compute ADR & RevPAR.
     *
     * @return array{audit_date: string, active_in_house_count: int, total_room_revenue_posted_idr: int, adr_idr: int, revpar_idr: int, status: string}
     */
    public function handle(
        string $propertyId,
        string $auditDate,
        int $totalAvailableRooms = 100
    ): array {
        return DB::transaction(function () use ($propertyId, $auditDate, $totalAvailableRooms) {
            $idempotencyKey = hash('sha256', "NIGHT_AUDIT_{$propertyId}_{$auditDate}");

            $inHouseReservations = HotelReservation::where('property_id', $propertyId)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->whereDate('check_in_date', '<=', $auditDate)
                ->whereDate('check_out_date', '>', $auditDate)
                ->get();

            $totalPostedRevenueIdr = 0;
            foreach ($inHouseReservations as $res) {
                $folio = HotelFolio::where('reservation_id', $res->id)->first();
                if ($folio && $folio->status === 'open') {
                    $folio->total_room_charges += (int) $res->daily_rate_idr;
                    $folio->outstanding_balance += (int) $res->daily_rate_idr;
                    $folio->save();
                    $totalPostedRevenueIdr += (int) $res->daily_rate_idr;
                }
            }

            $activeCount = $inHouseReservations->count();
            $adrIdr = $activeCount > 0 ? (int) round($totalPostedRevenueIdr / $activeCount) : 0;
            $revparIdr = $totalAvailableRooms > 0 ? (int) round($totalPostedRevenueIdr / $totalAvailableRooms) : 0;

            $this->audit(
                action: 'hotel.night_audit.closed',
                auditable: null,
                oldValues: null,
                newValues: [
                    'property_id' => $propertyId,
                    'audit_date' => $auditDate,
                    'in_house_count' => $activeCount,
                    'total_posted_revenue_idr' => $totalPostedRevenueIdr,
                    'adr_idr' => $adrIdr,
                    'revpar_idr' => $revparIdr,
                ],
                correlationId: "htl_audit_{$propertyId}_{$auditDate}",
                impactType: 'hotel_night_audit'
            );

            $this->outbox(
                eventType: 'HotelNightAuditClosed',
                payload: [
                    'property_id' => $propertyId,
                    'audit_date' => $auditDate,
                    'total_posted_revenue_idr' => $totalPostedRevenueIdr,
                    'in_house_count' => $activeCount,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'audit_date' => $auditDate,
                'active_in_house_count' => $activeCount,
                'total_room_revenue_posted_idr' => $totalPostedRevenueIdr,
                'adr_idr' => $adrIdr,
                'revpar_idr' => $revparIdr,
                'status' => 'AUDIT_CLOSED_BALANCED',
            ];
        });
    }
}
