<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;

class CloseEntertainmentEventAction
{
    public function __construct(
        private readonly Ledger $ledgerService
    ) {}

    /**
     * @return array{success: bool, event: EntertainmentEvent, total_unearned_recognized_idr: int, platform_fee_idr: int, organizer_net_share_idr: int}
     */
    public function handle(
        int $eventId,
        int $platformFeeBps = 500,
        string $idempotencyKey = ''
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        return DB::transaction(function () use ($eventId, $platformFeeBps, $idempotencyKey) {
            $event = EntertainmentEvent::lockForUpdate()->findOrFail($eventId);

            $tickets = EntertainmentTicket::where('event_id', $eventId)
                ->where('status', '!=', 'REFUNDED')
                ->get();

            $totalUnearned = (int) $tickets->sum('price_paid_idr');
            $platformFee = intdiv($totalUnearned * $platformFeeBps, 10000);
            $organizerNetShare = $totalUnearned - $platformFee;

            if ($event->status === 'CLOSED') {
                return [
                    'success' => true,
                    'event' => $event,
                    'total_unearned_recognized_idr' => $totalUnearned,
                    'platform_fee_idr' => $platformFee,
                    'organizer_net_share_idr' => $organizerNetShare,
                ];
            }

            if ($totalUnearned > 0) {
                // Post journal: Debit unearned liability, Credit ticket revenue & platform fee
                $this->ledgerService->post(new PostingDTO(
                    type: 'VENUE_EVENT_CLOSING',
                    description: "Event revenue recognition closing for {$event->event_code}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('ven:ticket_unearned:IDR', $totalUnearned)
                        ->credit('ven:ticket_revenue:IDR', $organizerNetShare)
                        ->credit('ven:platform_fee_revenue:IDR', $platformFee)
                        ->toEntries('IDR'),
                    referenceType: 'EVENT_CLOSING',
                    referenceId: (string) $event->id,
                ));
            }

            $event->update(['status' => 'CLOSED']);

            return [
                'success' => true,
                'event' => $event,
                'total_unearned_recognized_idr' => $totalUnearned,
                'platform_fee_idr' => $platformFee,
                'organizer_net_share_idr' => $organizerNetShare,
            ];
        });
    }
}
