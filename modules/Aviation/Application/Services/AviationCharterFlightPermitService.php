<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Carbon;

class AviationCharterFlightPermitService
{
    /**
     * Daftarkan pengajuan izin terbang carter / Flight Approval (FA) Direktorat Angkutan Udara.
     *
     * @param  string  $airlineCode  Kode maskapai carter
     * @param  string  $charterType  Tipe penerbangan carter (VIP, MEDEVAC, CARGO_SPECIAL, TOURIST)
     * @param  array<int, string>  $passengerManifest
     * @return array{
     *     permit_number: string,
     *     charter_type: string,
     *     route: string,
     *     status: string,
     *     is_diplomatic_clearance_required: bool,
     *     valid_until: string
     * }
     */
    public function submitFlightPermitApplication(
        string $airlineCode,
        string $charterType,
        string $originIata,
        string $destIata,
        string $departureDate,
        array $passengerManifest
    ): array {
        $type = strtoupper($charterType);
        $date = Carbon::parse($departureDate);

        // Medevac mendapatkan otorisasi darurat instan (Instant Emergency Clearance)
        $isEmergency = $type === 'MEDEVAC';
        $permitNumber = sprintf(
            'FA-%s-%s-%s',
            $isEmergency ? 'EMERG' : 'COMM',
            date('Ymd'),
            strtoupper(substr(uniqid('', true), -4))
        );

        $status = $isEmergency ? 'APPROVED_EXPEDITED' : 'UNDER_EVALUATION';
        $isDiplomaticRequired = false;

        return [
            'permit_number' => $permitNumber,
            'charter_type' => $type,
            'route' => strtoupper($originIata).'-'.strtoupper($destIata),
            'status' => $status,
            'is_diplomatic_clearance_required' => $isDiplomaticRequired,
            'valid_until' => $date->addDays(3)->toDateTimeString(),
        ];
    }
}
