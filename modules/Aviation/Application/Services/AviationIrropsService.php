<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Sagas\DisruptionIrropsSaga;
use Modules\Aviation\Domain\Models\AviationCareVoucher;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;

class AviationIrropsService
{
    public function __construct(
        protected DisruptionIrropsSaga $irropsSaga
    ) {}

    /**
     * Daftarkan insiden disrupsi operasional dan jalankan alur penanganan terpadu.
     *
     * @param array{
     *     flight_id: int,
     *     incident_category: string,
     *     delay_minutes: int,
     *     incident_description: string,
     *     alternative_flight_id?: int|null
     * } $data
     */
    public function handleFlightDisruption(array $data): array
    {
        return $this->irropsSaga->execute($data);
    }

    /**
     * Dapatkan detail kasus disrupsi lengkap dengan manifes penumpang dan status voucher.
     */
    public function getDisruptionCaseSummary(int $caseId): array
    {
        $case = AviationDisruptionCase::with(['flight', 'passengers.careVouchers', 'parametricClaims'])
            ->findOrFail($caseId);

        $vouchers = AviationCareVoucher::whereIn(
            'disruption_passenger_id',
            $case->passengers->pluck('id')
        )->get();

        return [
            'case_id' => $case->id,
            'case_number' => $case->case_number,
            'flight_number' => $case->flight->flight_number ?? 'N/A',
            'status' => $case->status,
            'incident_category' => $case->incident_category,
            'delay_minutes' => $case->delay_minutes,
            'regulatory_category' => $case->regulatory_category,
            'total_passengers' => $case->passengers->count(),
            'total_vouchers_issued' => $vouchers->count(),
            'total_vouchers_redeemed' => $vouchers->where('is_redeemed', true)->count(),
            'hotel_required' => $case->hotel_required,
            'cash_compensation_required' => $case->cash_compensation_required,
            'parametric_claims_count' => $case->parametricClaims->count(),
        ];
    }

    /**
     * Penebusan voucher konsumsi atau kompensasi di gerai bandara.
     */
    public function redeemCareVoucher(string $voucherCode, ?string $merchantCode = null): array
    {
        return DB::transaction(function () use ($voucherCode, $merchantCode) {
            $voucher = AviationCareVoucher::where('voucher_code', $voucherCode)
                ->lockForUpdate()
                ->firstOrFail();

            if ($voucher->is_redeemed) {
                throw new DomainException(sprintf('Voucher [%s] sudah pernah ditebus sebelumnya pada [%s].', $voucherCode, $voucher->redeemed_at));
            }

            if ($voucher->isExpired()) {
                throw new DomainException(sprintf('Voucher [%s] telah kedaluwarsa pada [%s].', $voucherCode, $voucher->expires_at));
            }

            $voucher->is_redeemed = true;
            $voucher->redeemed_at = now();
            $voucher->merchant_code = $merchantCode ?? 'MERCHANT-GENERAL-KIOSK';
            $voucher->save();

            return [
                'voucher_code' => $voucher->voucher_code,
                'voucher_type' => $voucher->voucher_type,
                'nominal_amount_minor_unit' => $voucher->nominal_amount_minor_unit,
                'status' => 'redeemed',
                'redeemed_at' => $voucher->redeemed_at->toIso8601String(),
            ];
        });
    }
}
