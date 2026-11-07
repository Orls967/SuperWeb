<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Illuminate\Support\Collection;
use Modules\Venue\Application\Actions\ExpireWaitlistOfferAction;
use Modules\Venue\Application\Actions\OfferSeatToNextWaitlistAction;
use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistGovernorInput;
use Modules\Venue\Domain\Algorithms\Waitlist\WaitlistPriorityGovernor;
use Modules\Venue\Domain\Models\TicketWaitlist;
use Modules\Venue\Domain\StateMachines\WaitlistStateMachine;

final class WaitlistQueueDispatcherService
{
    public function __construct(
        private readonly WaitlistPriorityGovernor $governor,
        private readonly OfferSeatToNextWaitlistAction $offerAction,
        private readonly ExpireWaitlistOfferAction $expireAction
    ) {}

    /**
     * Susun ulang urutan antrean waitlist berdasarkan algoritma prioritas V11.
     *
     * @return Collection<int, array{waitlist: TicketWaitlist, calculated_priority: int}>
     */
    public function recalculateAndRankQueue(int $eventId): Collection
    {
        $entries = TicketWaitlist::query()
            ->where('event_id', $eventId)
            ->where('status', WaitlistStateMachine::STATUS_WAITING)
            ->get();

        $scored = $entries->map(function (TicketWaitlist $entry): array {
            $input = new WaitlistGovernorInput(
                $entry->loyalty_points,
                $entry->registration_seconds_ago,
                $entry->is_fan_club_member,
                $entry->past_attendance_count
            );
            $result = $this->governor->evaluate($input);

            // Update skor di database
            $entry->priority_score = $result->priorityScore;
            $entry->save();

            return [
                'waitlist' => $entry,
                'calculated_priority' => $result->priorityScore,
            ];
        });

        return $scored->sortByDesc('calculated_priority')->values();
    }

    /**
     * Tawarkan tiket yang tersedia ke kandidat berikutnya dalam antrean.
     */
    public function dispatchNextCandidate(int $eventId, int $availableTicketId): ?TicketWaitlist
    {
        // 1. Kadaluwarsa penawaran yang sudah lewat batas waktu (15 menit)
        $this->expireAction->execute();

        // 2. Cari antrean WAITING dengan skor prioritas tertinggi
        $candidate = TicketWaitlist::query()
            ->where('event_id', $eventId)
            ->where('status', WaitlistStateMachine::STATUS_WAITING)
            ->orderBy('priority_score', 'desc')
            ->orderBy('id', 'asc')
            ->first();

        if ($candidate === null) {
            return null;
        }

        // 3. Tawarkan dengan window 15 menit
        $this->offerAction->execute($candidate, $availableTicketId);

        return $candidate->fresh();
    }
}
