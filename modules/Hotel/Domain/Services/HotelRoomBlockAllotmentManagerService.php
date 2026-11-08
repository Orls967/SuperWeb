<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Hotel\Domain\Algorithms\BlockAllotmentReleaseCertificate;
use Modules\Hotel\Domain\Algorithms\BlockAllotmentReleaser;
use Modules\Hotel\Domain\Models\RoomBlockAllotment;

final class HotelRoomBlockAllotmentManagerService
{
    public function __construct(
        private readonly BlockAllotmentReleaser $releaser
    ) {}

    public function processScheduledRelease(RoomBlockAllotment $allotment, ?DateTimeImmutable $currentDate = null): BlockAllotmentReleaseCertificate
    {
        return DB::transaction(function () use ($allotment, $currentDate) {
            /** @var RoomBlockAllotment $locked */
            $locked = RoomBlockAllotment::where('id', $allotment->id)->lockForUpdate()->firstOrFail();

            $today = $currentDate ?? new DateTimeImmutable;
            $checkIn = new DateTimeImmutable($locked->check_in_date);

            $diffDays = (int) $today->diff($checkIn)->format('%r%a');
            $daysUntil = max(0, $diffDays);

            $initial = (int) $locked->allotted_rooms_count;
            $confirmed = (int) $locked->confirmed_rooms_count;
            $currentAllocated = $initial - (int) $locked->released_rooms_count;

            $certificate = $this->releaser->release(
                propertyId: (string) ($locked->contract?->property_id ?? 'HTL-BALI-01'),
                blockId: $locked->block_code,
                initialRooms: $initial,
                currentAllocatedRooms: $currentAllocated,
                confirmedPickupRooms: $confirmed,
                daysUntilEvent: $daysUntil
            );

            if ($certificate->roomsReleasedToPublic > 0) {
                $locked->released_rooms_count += $certificate->roomsReleasedToPublic;
                if ($certificate->cutoffTier === BlockAllotmentReleaser::TIER_H7_FINAL) {
                    $locked->status = 'RELEASED';
                }
            }

            $locked->signature_hash = $certificate->signatureHash;
            $locked->save();

            return $certificate;
        });
    }

    public function recordPickup(RoomBlockAllotment $allotment, int $roomsCount = 1): void
    {
        DB::transaction(function () use ($allotment, $roomsCount) {
            /** @var RoomBlockAllotment $locked */
            $locked = RoomBlockAllotment::where('id', $allotment->id)->lockForUpdate()->firstOrFail();

            $available = $locked->allotted_rooms_count - $locked->released_rooms_count - $locked->confirmed_rooms_count;
            if ($roomsCount > $available) {
                throw new DomainException('Insufficient unpicked allotment in this room block.');
            }

            $locked->confirmed_rooms_count += $roomsCount;
            $locked->save();
        });
    }
}
