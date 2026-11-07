<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class WaitlistQueuePositionDTO
{
    public function __construct(
        public readonly int $waitlistId,
        public readonly int $userId,
        public readonly int $eventId,
        public readonly int $queueRank,
        public readonly int $computedPriorityScore,
        public readonly int $loyaltyPointsWeight,
        public readonly int $registrationRecencyWeight,
        public readonly string $status,
        public readonly ?\DateTimeImmutable $offerExpiresAt = null,
        public readonly ?string $reservationToken = null,
    ) {
        if ($this->waitlistId <= 0) {
            throw new \InvalidArgumentException('waitlistId harus positif');
        }
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException('userId harus positif');
        }
        if ($this->eventId <= 0) {
            throw new \InvalidArgumentException('eventId harus positif');
        }
        if ($this->queueRank <= 0) {
            throw new \InvalidArgumentException('queueRank harus positif');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'waitlist_id' => $this->waitlistId,
            'user_id' => $this->userId,
            'event_id' => $this->eventId,
            'queue_rank' => $this->queueRank,
            'computed_priority_score' => $this->computedPriorityScore,
            'loyalty_points_weight' => $this->loyaltyPointsWeight,
            'registration_recency_weight' => $this->registrationRecencyWeight,
            'status' => $this->status,
            'offer_expires_at' => $this->offerExpiresAt?->format(\DateTimeInterface::ATOM),
            'reservation_token' => $this->reservationToken,
        ];
    }
}
