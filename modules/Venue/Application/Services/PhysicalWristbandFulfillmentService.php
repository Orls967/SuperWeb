<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\VenueWristband;
use RuntimeException;

final class PhysicalWristbandFulfillmentService
{
    /**
     * Pair physical RFID/NFC wristband with gapless ticket hash chain.
     */
    public function pairWristband(EntertainmentTicket $ticket, string $wristbandUid): VenueWristband
    {
        if ($ticket->status !== 'issued' && $ticket->status !== 'scanned_in') {
            throw new RuntimeException("Tiket dengan status '{$ticket->status}' tidak dapat dipasangkan dengan wristband fisik.");
        }

        $existing = VenueWristband::where('wristband_uid', $wristbandUid)->first();
        if ($existing !== null && $existing->status === 'active') {
            throw new RuntimeException("UID Wristband '{$wristbandUid}' sudah aktif digunakan pada tiket lain.");
        }

        $publicKeyHash = hash('sha256', "nfc:salt:{$wristbandUid}:{$ticket->ticket_hash}");

        return VenueWristband::create([
            'ticket_id' => $ticket->id,
            'wristband_uid' => $wristbandUid,
            'status' => 'active',
            'nfc_public_key_hash' => $publicKeyHash,
            'issued_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Mark lost wristband, blacklist UID, and reissue new wristband for verified attendee.
     */
    public function replaceLostWristband(VenueWristband $oldBand, string $newUid): VenueWristband
    {
        $oldBand->status = 'lost';
        $oldBand->save();

        /** @var EntertainmentTicket $ticket */
        $ticket = $oldBand->ticket;

        return $this->pairWristband($ticket, $newUid);
    }
}
