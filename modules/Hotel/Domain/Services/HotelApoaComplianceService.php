<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Adapters\ApoaSimulationAdapter;
use Modules\Hotel\Domain\Models\HotelApoaReport;
use Modules\Hotel\Domain\StateMachines\ApoaReportStateMachine;

/**
 * Layanan Pelaporan Tamu Asing Sesuai UU No. 6 Tahun 2011 (APOA Imigrasi).
 */
final class HotelApoaComplianceService
{
    /**
     * @return array{apoa_report: HotelApoaReport, submitted: bool}
     */
    public function registerForeignGuestStay(
        string|int $reservationId,
        string $guestName,
        string $passportNumber,
        string $nationalityCode,
        string $checkInDate,
        string $checkOutDate,
        bool $isBaliPwaPaid = false
    ): array {
        $report = HotelApoaReport::create([
            'reservation_id' => (string) $reservationId,
            'passport_number' => $passportNumber,
            'nationality' => $nationalityCode,
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'status' => ApoaReportStateMachine::STATUS_PENDING,
            'is_bali_pwa_paid' => $isBaliPwaPaid,
        ]);

        $resIdInt = is_numeric($reservationId) ? (int) $reservationId : crc32((string) $reservationId);
        $simResult = ApoaSimulationAdapter::submitForeignerReport(
            reservationId: $resIdInt,
            passportNumber: $passportNumber,
            nationality: $nationalityCode
        );

        if ($simResult['status'] === 'ACCEPTED') {
            $sm = new ApoaReportStateMachine($report->status);
            $report->update([
                'status' => $sm->transitionTo(ApoaReportStateMachine::STATUS_SUBMITTED),
                'submitted_at' => CarbonImmutable::now(),
                'apoa_reference_number' => $simResult['apoa_tracking_number'],
            ]);
        }

        return [
            'apoa_report' => $report,
            'submitted' => $report->status === ApoaReportStateMachine::STATUS_SUBMITTED,
        ];
    }
}
