<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Venue\Application\Actions\ExpireWaitlistOfferAction;
use Modules\Venue\Application\Actions\OfferSeatToNextWaitlistAction;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\TicketWaitlist;
use Modules\Venue\Domain\StateMachines\WaitlistStateMachine;

final class WaitlistSeatOfferDispatcher
{
    public function __construct(
        private readonly OfferSeatToNextWaitlistAction $offerAction,
        private readonly ExpireWaitlistOfferAction $expireAction,
        private readonly WaitlistNotificationService $notificationService
    ) {}

    /**
     * Siklus periodik otomatis untuk melepaskan penawaran tiket batal ke kandidat teratas dalam antrean waitlist.
     *
     * @return array{
     *   expired_offers_count: int,
     *   dispatched_offers_count: int,
     *   details: array<int, array{waitlist_id: int, user_id: int, ticket_id: int}>
     * }
     */
    public function runPeriodicDispatch(int $eventId): array
    {
        return DB::transaction(function () use ($eventId): array {
            // 1. Bersihkan penawaran yang sudah melewati countdown window 15 menit
            $expiredOffers = $this->expireAction->execute();

            // 2. Cari tiket kategori yang statusnya ISSUED namun tidak memiliki pemilik aktif (atau dikembalikan dari pembatalan)
            $unclaimedTickets = EntertainmentTicket::query()
                ->where('event_id', $eventId)
                ->where('status', 'ISSUED')
                ->whereDoesntHave('activeWaitlistReservation')
                ->whereDoesntHave('activeResale')
                ->take(5)
                ->get();

            $dispatched = [];

            foreach ($unclaimedTickets as $ticket) {
                $topWaitingCandidate = TicketWaitlist::query()
                    ->where('event_id', $eventId)
                    ->where('status', WaitlistStateMachine::STATUS_WAITING)
                    ->orderBy('priority_score', 'desc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->first();

                if ($topWaitingCandidate === null) {
                    break;
                }

                $offered = $this->offerAction->execute($topWaitingCandidate, $ticket->id);
                $this->notificationService->dispatchOfferAlert($offered);

                $dispatched[] = [
                    'waitlist_id' => $offered->id,
                    'user_id' => $offered->user_id,
                    'ticket_id' => $ticket->id,
                ];
            }

            Log::info("Waitlist Periodic Dispatch selesai untuk Event #{$eventId}", [
                'expired_count' => $expiredOffers->count(),
                'dispatched_count' => count($dispatched),
            ]);

            return [
                'expired_offers_count' => $expiredOffers->count(),
                'dispatched_offers_count' => count($dispatched),
                'details' => $dispatched,
            ];
        });
    }
}
