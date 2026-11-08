<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

/**
 * Algoritma A9: Passenger Care Obligation Calculator.
 *
 * Menerjemahkan ketentuan Peraturan Menteri Perhubungan Republik Indonesia
 * PM 89 Tahun 2015 tentang Penanganan Keterlambatan Penerbangan (Delay Management)
 * menjadi kalkulasi hak kepedulian penumpang, voucer konsumsi, akomodasi hotel,
 * dan ganti rugi tunai secara deterministik.
 */
class PassengerCareObligationCalculator
{
    /**
     * Evaluasi hak kepedulian penumpang berdasarkan durasi keterlambatan dan kategori insiden.
     *
     * @param  int  $delayMinutes  Durasi keterlambatan penerbangan dalam menit
     * @param  string  $incidentCategory  Kategori penyebab insiden (AOG_TECHNICAL, CREW_FTL, FORCE_MAJEURE_WEATHER, FORCE_MAJEURE_VOLCANIC_ERUPTION, ATC_RESTRICTION, CANCELLATION)
     * @return array{
     *     category: string,
     *     delay_minutes: int,
     *     duty_of_care_required: bool,
     *     voucher_type: ?string,
     *     voucher_nominal_minor_unit: int,
     *     cash_compensation_required: bool,
     *     cash_compensation_amount_minor_unit: int,
     *     hotel_required: bool,
     *     reroute_option_required: bool,
     *     refund_option_required: bool,
     *     legal_basis: string
     * }
     */
    public function evaluateObligation(int $delayMinutes, string $incidentCategory): array
    {
        $normalizedIncident = strtoupper($incidentCategory);
        $isForceMajeure = str_starts_with($normalizedIncident, 'FORCE_MAJEURE') ||
            $normalizedIncident === 'ATC_RESTRICTION' ||
            $normalizedIncident === 'AIRPORT_INFRASTRUCTURE';

        $isCancellation = $normalizedIncident === 'CANCELLATION' || $delayMinutes >= 720;

        // Kategori 6: Pembatalan Penerbangan
        if ($isCancellation) {
            return [
                'category' => 'CAT6_CANCELLED',
                'delay_minutes' => $delayMinutes,
                'duty_of_care_required' => true,
                'voucher_type' => 'meal',
                'voucher_nominal_minor_unit' => 15000000, // Rp150.000 meal
                'cash_compensation_required' => ! $isForceMajeure,
                'cash_compensation_amount_minor_unit' => ! $isForceMajeure ? 30000000 : 0,
                'hotel_required' => true,
                'reroute_option_required' => true,
                'refund_option_required' => true,
                'legal_basis' => 'PM 89/2015 Pasal 3 ayat (6) & Pasal 9',
            ];
        }

        // Kategori 5: Keterlambatan lebih dari 240 menit (> 4 jam)
        if ($delayMinutes > 240) {
            return [
                'category' => 'CAT5',
                'delay_minutes' => $delayMinutes,
                'duty_of_care_required' => true,
                'voucher_type' => 'meal',
                'voucher_nominal_minor_unit' => 12500000, // Rp125.000 meal
                'cash_compensation_required' => ! $isForceMajeure, // Dikecualikan bila force majeure cuaca/bencana
                'cash_compensation_amount_minor_unit' => ! $isForceMajeure ? 30000000 : 0, // Tepat Rp300.000
                'hotel_required' => $delayMinutes >= 480, // > 8 jam atau melintasi tengah malam
                'reroute_option_required' => true,
                'refund_option_required' => true,
                'legal_basis' => 'PM 89/2015 Pasal 3 ayat (5) & Pasal 10',
            ];
        }

        // Kategori 4: Keterlambatan 181 s.d. 240 menit
        if ($delayMinutes >= 181) {
            return [
                'category' => 'CAT4',
                'delay_minutes' => $delayMinutes,
                'duty_of_care_required' => true,
                'voucher_type' => 'meal',
                'voucher_nominal_minor_unit' => 10000000, // Rp100.000
                'cash_compensation_required' => false,
                'cash_compensation_amount_minor_unit' => 0,
                'hotel_required' => false,
                'reroute_option_required' => true,
                'refund_option_required' => false,
                'legal_basis' => 'PM 89/2015 Pasal 3 ayat (4)',
            ];
        }

        // Kategori 3: Keterlambatan 121 s.d. 180 menit
        if ($delayMinutes >= 121) {
            return [
                'category' => 'CAT3',
                'delay_minutes' => $delayMinutes,
                'duty_of_care_required' => true,
                'voucher_type' => 'meal',
                'voucher_nominal_minor_unit' => 7500000, // Rp75.000
                'cash_compensation_required' => false,
                'cash_compensation_amount_minor_unit' => 0,
                'hotel_required' => false,
                'reroute_option_required' => false,
                'refund_option_required' => false,
                'legal_basis' => 'PM 89/2015 Pasal 3 ayat (3)',
            ];
        }

        // Kategori 2: Keterlambatan 61 s.d. 120 menit
        if ($delayMinutes >= 61) {
            return [
                'category' => 'CAT2',
                'delay_minutes' => $delayMinutes,
                'duty_of_care_required' => true,
                'voucher_type' => 'snack',
                'voucher_nominal_minor_unit' => 5000000, // Rp50.000
                'cash_compensation_required' => false,
                'cash_compensation_amount_minor_unit' => 0,
                'hotel_required' => false,
                'reroute_option_required' => false,
                'refund_option_required' => false,
                'legal_basis' => 'PM 89/2015 Pasal 3 ayat (2)',
            ];
        }

        // Kategori 1: Keterlambatan 30 s.d. 60 menit
        if ($delayMinutes >= 30) {
            return [
                'category' => 'CAT1',
                'delay_minutes' => $delayMinutes,
                'duty_of_care_required' => true,
                'voucher_type' => 'refreshment',
                'voucher_nominal_minor_unit' => 2500000, // Rp25.000
                'cash_compensation_required' => false,
                'cash_compensation_amount_minor_unit' => 0,
                'hotel_required' => false,
                'reroute_option_required' => false,
                'refund_option_required' => false,
                'legal_basis' => 'PM 89/2015 Pasal 3 ayat (1)',
            ];
        }

        // Di bawah 30 menit: Belum memenuhi ambang batas regulasi
        return [
            'category' => 'BELOW_THRESHOLD',
            'delay_minutes' => $delayMinutes,
            'duty_of_care_required' => false,
            'voucher_type' => null,
            'voucher_nominal_minor_unit' => 0,
            'cash_compensation_required' => false,
            'cash_compensation_amount_minor_unit' => 0,
            'hotel_required' => false,
            'reroute_option_required' => false,
            'refund_option_required' => false,
            'legal_basis' => 'PM 89/2015 Ketentuan Umum',
        ];
    }
}
