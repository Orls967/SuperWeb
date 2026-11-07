<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;

class HotelTimeshareOwnerUsagePointsConverter
{
    /**
     * Mengonversi alokasi minggu menginap pemilik timeshare menjadi Poin Loyalitas Global yang dapat ditukarkan di seluruh resort.
     *
     * @return array{owner_id: string, week_number: int, season: string, points_credited: int, conversion_fee_idr: int, ledger_account: string}
     */
    public function convertWeekToGlobalPoints(
        string $ownerId,
        int $weekNumber,
        string $season,
        int $standardWeekPointsBenchmark = 50000
    ): array {
        if ($weekNumber < 1 || $weekNumber > 52) {
            throw new InvalidArgumentException('Nomor minggu kalender harus antara 1 dan 52.');
        }

        $multiplier = match ($season) {
            'PEAK' => 1.50,
            'SHOULDER' => 1.00,
            default => 0.75,
        };

        $pointsCredited = (int) round($standardWeekPointsBenchmark * $multiplier);
        $conversionFeeIdr = 250000; // Biaya administrasi konversi hak tinggal ke poin

        return [
            'owner_id' => $ownerId,
            'week_number' => $weekNumber,
            'season' => $season,
            'points_credited' => $pointsCredited,
            'conversion_fee_idr' => $conversionFeeIdr,
            'ledger_account' => "htl:loyalty_points:{$ownerId}",
        ];
    }
}
