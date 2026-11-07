<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class DynamicBundleRequestDTO
{
    /**
     * @param  array<int, array{type: string, id: int, face_value_idr: int, title: string}>  $components
     */
    public function __construct(
        public readonly int $eventId,
        public readonly string $bundleName,
        public readonly string $bundleSlug,
        public readonly array $components,
        public readonly int $requestedDiscountBps,
        public readonly int $requestedFloorPriceIdr,
        public readonly ?int $hotelPackageId = null,
        public readonly ?int $shuttleRouteId = null,
    ) {
        if ($this->eventId <= 0) {
            throw new \InvalidArgumentException('eventId harus positif');
        }
        if (trim($this->bundleName) === '') {
            throw new \InvalidArgumentException('bundleName tidak boleh kosong');
        }
        if (count($this->components) === 0) {
            throw new \InvalidArgumentException('components minimal memiliki 1 elemen');
        }
        if ($this->requestedDiscountBps < 0 || $this->requestedDiscountBps > 5000) {
            throw new \InvalidArgumentException('requestedDiscountBps harus antara 0 dan 5000 bps (max 50%)');
        }
        if ($this->requestedFloorPriceIdr < 0) {
            throw new \InvalidArgumentException('requestedFloorPriceIdr tidak boleh negatif');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'bundle_name' => $this->bundleName,
            'bundle_slug' => $this->bundleSlug,
            'components' => $this->components,
            'requested_discount_bps' => $this->requestedDiscountBps,
            'requested_floor_price_idr' => $this->requestedFloorPriceIdr,
            'hotel_package_id' => $this->hotelPackageId,
            'shuttle_route_id' => $this->shuttleRouteId,
        ];
    }
}
