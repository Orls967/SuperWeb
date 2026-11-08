<?php

declare(strict_types=1);

namespace Modules\Aviation\Integrations;

use Illuminate\Support\Facades\Log;

class FinanceAviationLedgerService
{
    /**
     * Catat jurnal akuntansi penyelesaian kliring codeshare antar-maskapai.
     *
     * @param  string  $settlementRef  Referensi unik kliring
     * @param  int  $operatingShareMinorUnit  Bagian maskapai pengoperasi (kredit pendapatan)
     * @param  int  $marketingShareMinorUnit  Bagian maskapai penjual (komisi agen)
     * @param  int  $operatingAirlineId  ID maskapai pengoperasi
     * @param  int  $marketingAirlineId  ID maskapai penjual
     */
    public function recordCodeshareSettlement(
        string $settlementRef,
        int $operatingShareMinorUnit,
        int $marketingShareMinorUnit,
        int $operatingAirlineId,
        int $marketingAirlineId
    ): void {
        Log::info('Codeshare interline settlement journal posted', [
            'settlement_ref' => $settlementRef,
            'operating_share_minor_unit' => $operatingShareMinorUnit,
            'marketing_share_minor_unit' => $marketingShareMinorUnit,
            'operating_airline_id' => $operatingAirlineId,
            'marketing_airline_id' => $marketingAirlineId,
            'debit_account' => '1150-AVI-RECV-INTERLINE-CLEARING',
            'credit_account_operating' => '4110-AVI-REV-CODESHARE-FLOWN',
            'credit_account_marketing' => '2150-AVI-PAYABLE-PARTNER-COMMISSION',
        ]);
    }

    /**
     * Catat liabilitas penerbitan voucher kompensasi delay IRROPS.
     */
    public function recordVoucherLiability(
        string $voucherCode,
        string $voucherType,
        int $nominalMinorUnit
    ): void {
        Log::info('IRROPS care voucher liability posted to ledger', [
            'voucher_code' => $voucherCode,
            'voucher_type' => $voucherType,
            'nominal_minor_unit' => $nominalMinorUnit,
            'debit_expense_account' => '6100-AVI-EXP-IRROPS-CARE',
            'credit_liability_account' => '2100-AVI-LIAB-VOUCHER-PAYABLE',
        ]);
    }
}
