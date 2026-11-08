<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use DateTimeImmutable;
use Modules\Hotel\Domain\Algorithms\BlockAllotmentReleaseCertificate;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;
use Modules\Hotel\Domain\Services\HotelRoomBlockAllotmentManagerService;

final class ReleaseExpiredRoomBlockAllotmentsAction
{
    public function __construct(
        private readonly HotelRoomBlockAllotmentManagerService $allotmentManager
    ) {}

    /**
     * @return array<int, BlockAllotmentReleaseCertificate>
     */
    public function execute(?string $propertyId = null, ?DateTimeImmutable $currentDate = null): array
    {
        $query = RoomBlockAllotment::whereIn('status', ['HELD', 'CONTRACTED']);

        if ($propertyId !== null) {
            $query->whereHas('contract', function ($q) use ($propertyId) {
                $q->where('property_id', $propertyId);
            });
        }

        $allotments = $query->get();
        $certificates = [];

        foreach ($allotments as $allotment) {
            $cert = $this->allotmentManager->processScheduledRelease($allotment, $currentDate);
            if ($cert->roomsReleasedToPublic > 0) {
                $certificates[] = $cert;
            }
        }

        return $certificates;
    }
}
