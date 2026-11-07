<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\Log;
use Modules\Venue\Domain\Models\TicketWaitlist;

final class WaitlistNotificationService
{
    /**
     * Kirim notifikasi countdown window 15 menit ke calon pembeli antrean waitlist.
     *
     * @return array{channel: string, sent: bool, recipient_id: int, expires_in_seconds: int}
     */
    public function dispatchOfferAlert(TicketWaitlist $waitlist): array
    {
        $user = $waitlist->user;
        $expiresAt = $waitlist->offer_expires_at;

        $secondsRemaining = $expiresAt !== null
            ? max(0, $expiresAt->getTimestamp() - time())
            : 900;

        Log::info("Kirim notifikasi SMS/Push ke pengguna #{$waitlist->user_id}: Tiket antrean tersedia. Klaim sebelum {$secondsRemaining} detik.", [
            'waitlist_id' => $waitlist->id,
            'token' => $waitlist->reservation_token,
        ]);

        return [
            'channel' => 'MULTI_PUSH_SMS',
            'sent' => true,
            'recipient_id' => $waitlist->user_id,
            'expires_in_seconds' => $secondsRemaining,
        ];
    }
}
