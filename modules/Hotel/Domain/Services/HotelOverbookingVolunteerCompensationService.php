<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

/**
 * Layanan Perekrutan Relawan Pembatalan Sukarela Saat Overbooking (Denied Boarding Compensation).
 */
final class HotelOverbookingVolunteerCompensationService
{
    /**
     * @return array{volunteer_accepted: bool, compensation_package_idr: int, alternative_stay_date: string}
     */
    public function offerVolunteerPackage(string|int $reservationId, int $dailyRateIdr): array
    {
        // Tawaran voucher 150% tarif kamar + menginap gratis di tanggal lain
        $compensation = (int) round($dailyRateIdr * 1.50);
        $nextDate = date('Y-m-d', strtotime('+7 days'));

        return [
            'volunteer_accepted' => true,
            'compensation_package_idr' => $compensation,
            'alternative_stay_date' => $nextDate,
        ];
    }
}
