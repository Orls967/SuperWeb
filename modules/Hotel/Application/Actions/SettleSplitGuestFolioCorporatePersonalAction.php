<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Shared\Application\BaseAction;

class SettleSplitGuestFolioCorporatePersonalAction extends BaseAction
{
    /**
     * Settle guest folio multi-payer split between Corporate City Ledger (AR) and Guest Personal Payment.
     *
     * @return array{folio: HotelFolio, corporate_room_share_idr: int, guest_personal_addon_idr: int, total_settled_idr: int}
     */
    public function handle(
        string $folioId,
        int $corporateRoomShareIdr,
        int $guestPersonalAddonIdr
    ): array {
        return DB::transaction(function () use ($folioId, $corporateRoomShareIdr, $guestPersonalAddonIdr) {
            $folio = HotelFolio::lockForUpdate()->findOrFail($folioId);

            $totalSettledIdr = $corporateRoomShareIdr + $guestPersonalAddonIdr;

            if ($totalSettledIdr !== (int) $folio->outstanding_balance) {
                throw new \InvalidArgumentException(
                    "Split settlement total (IDR {$totalSettledIdr}) does not equal folio outstanding balance (IDR {$folio->outstanding_balance})."
                );
            }

            $folio->total_paid += $totalSettledIdr;
            $folio->outstanding_balance = 0;
            $folio->status = 'settled';
            $folio->save();

            $this->audit(
                action: 'hotel.folio.split_settled',
                auditable: $folio,
                oldValues: ['status' => 'open'],
                newValues: [
                    'folio_id' => $folio->id,
                    'corporate_share_idr' => $corporateRoomShareIdr,
                    'guest_share_idr' => $guestPersonalAddonIdr,
                    'total_settled_idr' => $totalSettledIdr,
                ],
                correlationId: "htl_folio_split_{$folio->id}",
                impactType: 'folio_settlement'
            );

            $this->outbox(
                eventType: 'GuestFolioSplitSettled',
                payload: [
                    'folio_id' => $folio->id,
                    'corporate_share_idr' => $corporateRoomShareIdr,
                    'guest_share_idr' => $guestPersonalAddonIdr,
                ],
                idempotencyKey: hash('sha256', "folio_split_{$folio->id}")
            );

            return [
                'folio' => $folio,
                'corporate_room_share_idr' => $corporateRoomShareIdr,
                'guest_personal_addon_idr' => $guestPersonalAddonIdr,
                'total_settled_idr' => $totalSettledIdr,
            ];
        });
    }
}
