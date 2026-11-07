<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Hotel\Domain\Adapters\ApoaSimulationAdapter;
use Modules\Hotel\Domain\Models\HotelApoaReport;
use Modules\Hotel\Domain\StateMachines\ApoaReportStateMachine;

/**
 * Tindakan Rekonsiliasi & Pengiriman Ulang Antrean Tamu Asing APOA Tertunda.
 */
final class ReconcileApoaForeignGuestQueueAction
{
    /**
     * @return array{total_pending: int, successfully_reported: int}
     */
    public function execute(): array
    {
        $pendingReports = HotelApoaReport::where('status', ApoaReportStateMachine::STATUS_PENDING)->get();
        $successCount = 0;

        foreach ($pendingReports as $report) {
            $resIdInt = is_numeric($report->reservation_id) ? (int) $report->reservation_id : crc32((string) $report->reservation_id);
            $res = ApoaSimulationAdapter::submitForeignerReport(
                reservationId: $resIdInt,
                passportNumber: $report->passport_number,
                nationality: $report->nationality
            );

            if ($res['status'] === 'ACCEPTED') {
                $sm = new ApoaReportStateMachine($report->status);
                $report->update([
                    'status' => $sm->transitionTo(ApoaReportStateMachine::STATUS_SUBMITTED),
                    'submitted_at' => CarbonImmutable::now(),
                    'apoa_reference_number' => $res['apoa_tracking_number'],
                ]);
                $successCount++;
            }
        }

        return [
            'total_pending' => count($pendingReports),
            'successfully_reported' => $successCount,
        ];
    }
}
