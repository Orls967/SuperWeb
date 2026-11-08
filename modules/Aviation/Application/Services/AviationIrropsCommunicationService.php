<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Facades\Log;
use Modules\Aviation\Domain\Models\AviationCareVoucher;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;

class AviationIrropsCommunicationService
{
    /**
     * Kirimkan pemberitahuan resmi dan tautan e-voucher kepada seluruh penumpang kasus disrupsi.
     *
     * @return array{case_id: int, notifications_sent: int, failed_transmissions: int}
     */
    public function dispatchPassengerBroadcast(int $disruptionCaseId): array
    {
        $case = AviationDisruptionCase::with(['flight', 'passengers.careVouchers'])
            ->findOrFail($disruptionCaseId);

        $sentCount = 0;
        $failedCount = 0;

        foreach ($case->passengers as $pax) {
            try {
                $voucherLinks = $pax->careVouchers->map(
                    fn (AviationCareVoucher $v) => sprintf(
                        '%s (%s: Rp%s)',
                        $v->voucher_code,
                        strtoupper($v->voucher_type),
                        number_format($v->nominal_amount_minor_unit / 100, 0, ',', '.')
                    )
                )->implode(', ');

                $hotelInfo = $pax->hotel_reservation_ref !== null
                    ? sprintf(' Akomodasi Hotel Anda: Ref #%s.', $pax->hotel_reservation_ref)
                    : '';

                $message = sprintf(
                    'Pemberitahuan Resmi Penerbangan %s: Mohon maaf atas keterlambatan %d menit (%s). Paket kompensasi PM 89 Anda: [%s].%s Bawa kode QR voucher Anda ke kasir merchant bandara.',
                    $case->flight->flight_number ?? 'Penerbangan',
                    $case->delay_minutes,
                    $case->regulatory_category,
                    $voucherLinks ?: 'Dalam pemrosesan',
                    $hotelInfo
                );

                Log::info('IRROPS Passenger Broadcast Dispatched', [
                    'passenger_name' => $pax->passenger_name,
                    'case_number' => $case->case_number,
                    'message' => $message,
                ]);

                $sentCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                Log::warning('Gagal mendistribusikan notifikasi disrupsi', [
                    'passenger_id' => $pax->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'case_id' => $case->id,
            'notifications_sent' => $sentCount,
            'failed_transmissions' => $failedCount,
        ];
    }
}
