<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Aviation\Domain\Models\AviationCareVoucher;

class AviationIrropsVoucherSettlementService
{
    /**
     * Rekonsiliasi dan pelunasan faktur tagihan e-voucher yang telah ditebus merchant mitra bandara.
     *
     * @return array{
     *     merchant_id: string,
     *     vouchers_settled_count: int,
     *     total_disbursement_idr: int,
     *     settlement_reference: string,
     *     status: string
     * }
     */
    public function settleMerchantVouchers(string $merchantCode): array
    {
        return DB::transaction(function () use ($merchantCode) {
            $vouchers = AviationCareVoucher::where('merchant_code', $merchantCode)
                ->where('is_redeemed', true)
                ->lockForUpdate()
                ->get();

            $totalMinorUnit = (int) $vouchers->sum('nominal_amount_minor_unit');
            $count = $vouchers->count();

            $ref = sprintf('SETTLE-VCR-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -4)));

            $totalIdr = (int) round($totalMinorUnit / 100);

            return [
                'merchant_code' => $merchantCode,
                'vouchers_settled_count' => $count,
                'total_disbursement_idr' => $totalIdr,
                'settlement_reference' => $ref,
                'status' => 'DISBURSED_TO_MERCHANT',
            ];
        });
    }
}
