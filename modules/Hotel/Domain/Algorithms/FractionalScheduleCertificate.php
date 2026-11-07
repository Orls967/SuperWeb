<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Data Nilai Sertifikat Jadwal Penggunaan Hak Timeshare / Fraksional Villa.
 */
final class FractionalScheduleCertificate
{
    /**
     * @param  array<int, array{week_number: int, season: string, status: string, start_date: string, end_date: string}>  $scheduledWeeks
     */
    public function __construct(
        public readonly string $unitId,
        public readonly string $ownerId,
        public readonly int $totalFractionsOwned,
        public readonly int $allocatedWeeksCount,
        public readonly int $rentalPoolWeeksCount,
        public readonly array $scheduledWeeks,
        public readonly string $checksum,
    ) {}
}
