<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Algorithms;

use Modules\Shared\Domain\Algorithms\Aviation\PassengerCareObligationCalculator as SharedCalculator;

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
    private readonly SharedCalculator $engine;

    public function __construct(?SharedCalculator $engine = null)
    {
        $this->engine = $engine ?? new SharedCalculator;
    }

    /**
     * Evaluasi hak kepedulian penumpang berdasarkan durasi keterlambatan dan kategori insiden.
     *
     * @param  int  $delayMinutes  Durasi keterlambatan penerbangan dalam menit
     * @param  string  $incidentCategory  Kategori penyebab insiden
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
        $res = $this->engine->evaluate($delayMinutes, $incidentCategory);

        $legalBasis = match ($res->category) {
            'CAT6_CANCELLED' => 'PM 89/2015 Pasal 3 ayat (6) & Pasal 9',
            'CAT5' => 'PM 89/2015 Pasal 3 ayat (5) & Pasal 9',
            'CAT4' => 'PM 89/2015 Pasal 3 ayat (4)',
            'CAT3' => 'PM 89/2015 Pasal 3 ayat (3)',
            'CAT2' => 'PM 89/2015 Pasal 3 ayat (2)',
            'CAT1' => 'PM 89/2015 Pasal 3 ayat (1)',
            default => 'PM 89/2015 Ketentuan Umum',
        };

        $isCancellation = ($res->category === 'CAT6_CANCELLED');
        $isRerouteRequired = $isCancellation || ($delayMinutes >= 180);
        $isRefundRequired = $isCancellation || ($delayMinutes >= 180);

        return [
            'category' => $res->category,
            'delay_minutes' => $delayMinutes,
            'duty_of_care_required' => $res->dutyOfCareRequired,
            'voucher_type' => $res->voucherType,
            'voucher_nominal_minor_unit' => $res->voucherNominalMinorUnit,
            'cash_compensation_required' => $res->cashCompensationRequired,
            'cash_compensation_amount_minor_unit' => $res->cashCompensationAmountMinorUnit,
            'hotel_required' => $res->hotelRequired,
            'reroute_option_required' => $isRerouteRequired,
            'refund_option_required' => $isRefundRequired,
            'legal_basis' => $legalBasis,
        ];
    }
}
