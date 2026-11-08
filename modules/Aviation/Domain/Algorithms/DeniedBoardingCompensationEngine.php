<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

/**
 * Algoritma: Denied Boarding Compensation Engine.
 *
 * Mengelola lelang sukarela (voluntary denied boarding auction) dan santunan
 * penolakan terbang terpaksa (involuntary denied boarding compensation)
 * sesuai Pasal 11 PM 89/2015 dan standar industri IATA.
 */
class DeniedBoardingCompensationEngine
{
    /**
     * Hitung tawaran kompensasi lelang sukarela untuk mencari penumpang yang bersedia melepas kursinya.
     *
     * @param  int  $baseFareMinorUnit  Tarif tiket penumpang
     * @param  int  $nextFlightDelayHours  Selisih waktu menuju penerbangan pengganti dalam jam
     * @return array{
     *     cash_compensation_minor_unit: int,
     *     travel_voucher_minor_unit: int,
     *     upgrade_to_business_eligible: bool,
     *     meal_voucher_included: bool,
     *     hotel_included: bool
     * }
     */
    public function generateVoluntaryRelinquishOffer(int $baseFareMinorUnit, int $nextFlightDelayHours): array
    {
        // Insentif tunai minimal 50% dari harga tiket atau minimal Rp500.000,-
        $cashMin = 50000000;
        $calculatedCash = (int) round($baseFareMinorUnit * 0.5);
        $cashComp = max($cashMin, $calculatedCash);

        // Insentif travel voucher bernilai 150% dari santunan tunai untuk mendorong retensi pelanggan
        $voucherComp = (int) round($cashComp * 1.5);

        $hotelIncluded = $nextFlightDelayHours >= 8;
        $mealIncluded = $nextFlightDelayHours >= 2;
        $upgradeEligible = $nextFlightDelayHours >= 4;

        return [
            'cash_compensation_minor_unit' => $cashComp,
            'travel_voucher_minor_unit' => $voucherComp,
            'upgrade_to_business_eligible' => $upgradeEligible,
            'meal_voucher_included' => $mealIncluded,
            'hotel_included' => $hotelIncluded,
        ];
    }

    /**
     * Hitung santunan wajib penolakan terbang terpaksa (Involuntary Denied Boarding).
     * Penumpang berhak atas ganti rugi tunai penuh, pengalihan penerbangan, atau pengembalian dana 100%.
     *
     * @return array{
     *     mandatory_cash_minor_unit: int,
     *     full_refund_minor_unit: int,
     *     immediate_care_package: string,
     *     regulatory_reference: string
     * }
     */
    public function calculateInvoluntaryCompensation(int $ticketPriceMinorUnit, bool $isInternationalFlight = false): array
    {
        // Standar domestik: Tiket penuh di-refund ATAU penerbangan gratis + Rp1.000.000 tunai
        $mandatoryCash = $isInternationalFlight ? 300000000 : 100000000; // Rp3.000.000 intl, Rp1.000.000 dom

        return [
            'mandatory_cash_minor_unit' => $mandatoryCash,
            'full_refund_minor_unit' => $ticketPriceMinorUnit,
            'immediate_care_package' => 'Executive Lounge Access, Heavy Meal, Priority Rebooking',
            'regulatory_reference' => 'PM 89/2015 Pasal 11 & Ketentuan Perlindungan Konsumen IATA',
        ];
    }
}
