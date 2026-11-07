<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Algorithms\FractionalScheduleCertificate;
use Modules\Hotel\Domain\Algorithms\FractionalScheduleCertificateVerifier;
use Modules\Hotel\Domain\Algorithms\FractionalUsageScheduler;
use Modules\Hotel\Domain\Models\TimeshareUnit;
use RuntimeException;

/**
 * Tindakan Penjadwalan Hak Tinggal Timeshare & Alokasi Rental Pool (Algoritma H8).
 */
final class ScheduleTimeshareUsageAction
{
    public function __construct(
        private readonly FractionalUsageScheduler $scheduler,
        private readonly FractionalScheduleCertificateVerifier $verifier,
    ) {}

    /**
     * @param  array<int, int>  $requestedOwnerWeeks
     */
    public function execute(
        string $unitId,
        string $ownerId,
        int $fractionsOwned,
        array $requestedOwnerWeeks,
        int $calendarYear
    ): FractionalScheduleCertificate {
        $unit = TimeshareUnit::findOrFail($unitId);

        $cert = $this->scheduler->schedule(
            unitId: $unit->id,
            ownerId: $ownerId,
            fractionsOwned: $fractionsOwned,
            requestedOwnerWeeks: $requestedOwnerWeeks,
            calendarYear: $calendarYear
        );

        if (! $this->verifier->verify($cert)) {
            throw new RuntimeException("Verifikasi sertifikat FractionalSchedule gagal untuk unit {$unitId}");
        }

        return $cert;
    }
}
