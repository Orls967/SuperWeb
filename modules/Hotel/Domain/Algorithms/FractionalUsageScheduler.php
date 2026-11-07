<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

/**
 * Algoritma Bersertifikat H8: FractionalUsageScheduler.
 * Mengalokasikan hak minggu tinggal villa/timeshare dan mengarahkan
 * minggu yang tidak digunakan ke rental pool umum properti.
 */
final class FractionalUsageScheduler
{
    /**
     * @param  array<int, int>  $requestedOwnerWeeks  Daftar minggu (1-52) yang diminta pemilik untuk ditinggali
     */
    public function schedule(
        string $unitId,
        string $ownerId,
        int $fractionsOwned,
        array $requestedOwnerWeeks,
        int $calendarYear
    ): FractionalScheduleCertificate {
        $fractionsCount = max(1, min(52, $fractionsOwned));

        // Sanitasi dan dedup minggu permintaan pemilik
        $uniqueRequested = array_values(array_unique(array_filter(
            $requestedOwnerWeeks,
            fn (int $w) => $w >= 1 && $w <= 52
        )));

        $scheduled = [];
        $ownerAllocated = 0;
        $rentalAllocated = 0;

        // Alokasikan minggu yang diminta pemilik hingga batas kuota fraksi
        foreach ($uniqueRequested as $w) {
            if (count($scheduled) >= $fractionsCount) {
                break;
            }
            $season = $this->determineSeason($w);
            $dates = $this->computeWeekDates($calendarYear, $w);

            $scheduled[] = [
                'week_number' => $w,
                'season' => $season,
                'status' => 'OWNER_OCCUPIED',
                'start_date' => $dates['start'],
                'end_date' => $dates['end'],
            ];
            $ownerAllocated++;
        }

        // Jika sisa hak fraksi belum habis, minggu yang tidak diminta dialihkan ke RENTAL_POOL
        $nextAvailableWeek = 1;
        while (count($scheduled) < $fractionsCount) {
            $alreadyTaken = false;
            foreach ($scheduled as $s) {
                if ($s['week_number'] === $nextAvailableWeek) {
                    $alreadyTaken = true;
                    break;
                }
            }

            if (! $alreadyTaken) {
                $season = $this->determineSeason($nextAvailableWeek);
                $dates = $this->computeWeekDates($calendarYear, $nextAvailableWeek);

                $scheduled[] = [
                    'week_number' => $nextAvailableWeek,
                    'season' => $season,
                    'status' => 'RENTAL_POOL',
                    'start_date' => $dates['start'],
                    'end_date' => $dates['end'],
                ];
                $rentalAllocated++;
            }

            $nextAvailableWeek++;
            if ($nextAvailableWeek > 52) {
                break;
            }
        }

        // Urutkan berdasarkan nomor minggu
        usort($scheduled, fn ($a, $b) => $a['week_number'] <=> $b['week_number']);

        $checksum = hash('sha256', sprintf(
            '%s|%s|%d|%d|%d|%d',
            $unitId,
            $ownerId,
            $fractionsCount,
            $ownerAllocated,
            $rentalAllocated,
            count($scheduled)
        ));

        return new FractionalScheduleCertificate(
            unitId: $unitId,
            ownerId: $ownerId,
            totalFractionsOwned: $fractionsCount,
            allocatedWeeksCount: $ownerAllocated,
            rentalPoolWeeksCount: $rentalAllocated,
            scheduledWeeks: $scheduled,
            checksum: $checksum
        );
    }

    private function determineSeason(int $week): string
    {
        return match (true) {
            $week >= 24 && $week <= 32 => 'PEAK_SUMMER',
            $week >= 50 || $week <= 2 => 'PEAK_YEAR_END',
            $week >= 13 && $week <= 18 => 'HIGH_SEASON',
            default => 'LOW_SEASON',
        };
    }

    /**
     * @return array{start: string, end: string}
     */
    private function computeWeekDates(int $year, int $week): array
    {
        $dto = new \DateTimeImmutable;
        $start = $dto->setISODate($year, $week, 1)->format('Y-m-d');
        $end = $dto->setISODate($year, $week, 7)->format('Y-m-d');

        return ['start' => $start, 'end' => $end];
    }
}
