<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelReservation;

/**
 * Tindakan Audit Kepatuhan Tarif Kontrak Korporat & Peringatan Penyimpangan Tarif.
 */
final class AuditCorporateContractComplianceAction
{
    /**
     * @return array{total_corporate_bookings: int, compliant_bookings: int, rate_discrepancies_count: int, is_compliant: bool}
     */
    public function execute(string $startDate, string $endDate): array
    {
        $reservations = HotelReservation::whereBetween('check_in_date', [$startDate, $endDate])
            ->whereNotNull('guest_email')
            ->get();

        $corporateCount = 0;
        $compliantCount = 0;
        $discrepancyCount = 0;

        foreach ($reservations as $rsv) {
            if (str_ends_with((string) $rsv->guest_email, '@pertamina.com') || str_ends_with((string) $rsv->guest_email, '@telkom.co.id')) {
                $corporateCount++;
                // Kontrak corporate tarif flat Rp 950.000
                if ((int) $rsv->daily_rate_idr === 950000) {
                    $compliantCount++;
                } else {
                    $discrepancyCount++;
                }
            }
        }

        return [
            'total_corporate_bookings' => $corporateCount,
            'compliant_bookings' => $compliantCount,
            'rate_discrepancies_count' => $discrepancyCount,
            'is_compliant' => $discrepancyCount === 0,
        ];
    }
}
