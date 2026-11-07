<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class SecondaryResaleListingDTO
{
    public function __construct(
        public readonly int $ticketId,
        public readonly int $sellerUserId,
        public readonly int $resalePriceIdr,
        public readonly int $faceValueIdr,
        public readonly ?string $notes = null,
        public readonly ?string $listingIp = null,
        public readonly ?string $deviceFingerprint = null,
    ) {
        if ($this->ticketId <= 0) {
            throw new \InvalidArgumentException('ticketId harus positif');
        }
        if ($this->sellerUserId <= 0) {
            throw new \InvalidArgumentException('sellerUserId harus positif');
        }
        if ($this->resalePriceIdr <= 0) {
            throw new \InvalidArgumentException('resalePriceIdr harus positif');
        }
        if ($this->faceValueIdr <= 0) {
            throw new \InvalidArgumentException('faceValueIdr harus positif');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'seller_user_id' => $this->sellerUserId,
            'resale_price_idr' => $this->resalePriceIdr,
            'face_value_idr' => $this->faceValueIdr,
            'notes' => $this->notes,
            'listing_ip' => $this->listingIp,
            'device_fingerprint' => $this->deviceFingerprint,
        ];
    }
}
