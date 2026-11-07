<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class WaitlistSeatClaimResultDTO
{
    public function __construct(
        public readonly int $waitlistId,
        public readonly int $ticketId,
        public readonly int $userId,
        public readonly bool $isSuccess,
        public readonly string $claimReference,
        public readonly int $settledAmountIdr,
        public readonly \DateTimeImmutable $claimedAt,
        public readonly ?string $errorMessage = null,
    ) {
        if ($this->waitlistId <= 0) {
            throw new \InvalidArgumentException('waitlistId harus positif');
        }
        if ($this->ticketId <= 0) {
            throw new \InvalidArgumentException('ticketId harus positif');
        }
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException('userId harus positif');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'waitlist_id' => $this->waitlistId,
            'ticket_id' => $this->ticketId,
            'user_id' => $this->userId,
            'is_success' => $this->isSuccess,
            'claim_reference' => $this->claimReference,
            'settled_amount_idr' => $this->settledAmountIdr,
            'claimed_at' => $this->claimedAt->format(\DateTimeInterface::ATOM),
            'error_message' => $this->errorMessage,
        ];
    }
}
