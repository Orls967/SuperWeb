<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Venue\Application\Actions\OfferSeatToNextWaitlistAction;
use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistGovernorInput;
use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistPriorityGovernor;
use Modules\Venue\Domain\Models\TicketWaitlist;
use Modules\Venue\Domain\StateMachines\WaitlistStateMachine;

final class WaitlistFairAllocationEngine
{
    public function __construct(
        private readonly WaitlistPriorityGovernor $governor,
        private readonly OfferSeatToNextWaitlistAction $offerAction
    ) {}

    /**
     * Alokasikan tiket yang baru tersedia ke antrean waitlist secara adil dan anti-starvation.
     *
     * @param  array<int, int>  $availableTicketIds
     * @return array{
     *   allocated_count: int,
     *   remaining_tickets_count: int,
     *   offered_pairs: array<int, array{waitlist_id: int, ticket_id: int, user_id: int}>
     * }
     */
    public function allocateBatch(int $eventId, array $availableTicketIds): array
    {
        return DB::transaction(function () use ($eventId, $availableTicketIds): array {
            $allocatedPairs = [];
            $ticketQueue = $availableTicketIds;

            // Ambil kandidat antrean status WAITING
            $candidates = TicketWaitlist::query()
                ->where('event_id', $eventId)
                ->where('status', WaitlistStateMachine::STATUS_WAITING)
                ->lockForUpdate()
                ->get();

            // Beri bobot skor menggunakan V11
            $scored = $candidates->map(function (TicketWaitlist $c): array {
                $input = new WaitlistGovernorInput(
                    $c->loyalty_points,
                    $c->registration_seconds_ago,
                    $c->is_fan_club_member,
                    $c->past_attendance_count
                );
                $res = $this->governor->evaluate($input);
                $c->priority_score = $res->priorityScore;
                $c->save();

                return [
                    'model' => $c,
                    'score' => $res->priorityScore,
                ];
            });

            // Urutkan kandidat tertinggi
            $sorted = $scored->sortByDesc('score')->values();

            foreach ($sorted as $item) {
                if (count($ticketQueue) === 0) {
                    break;
                }

                $ticketId = array_shift($ticketQueue);
                /** @var TicketWaitlist $waitlistModel */
                $waitlistModel = $item['model'];

                $this->offerAction->execute($waitlistModel, $ticketId);

                $allocatedPairs[] = [
                    'waitlist_id' => $waitlistModel->id,
                    'ticket_id' => $ticketId,
                    'user_id' => $waitlistModel->user_id,
                ];
            }

            return [
                'allocated_count' => count($allocatedPairs),
                'remaining_tickets_count' => count($ticketQueue),
                'offered_pairs' => $allocatedPairs,
            ];
        });
    }
}
