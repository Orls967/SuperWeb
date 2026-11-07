<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Ledger;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;
use Modules\Hotel\Domain\Models\HtlSubFolio;

class HotelCityLedgerDoubleEntryService
{
    /**
     * Membukukan pemindahan piutang dari sub-folio tamu ke akun buku besar piutang usaha korporat.
     *
     * Debit: htl:city_ledger:IDR
     * Kredit: htl:guest_folio:{folio_id}:IDR
     *
     * @return array{journal_ref: string, corporate_id: string, transferred_amount_idr: int, ledger_debit: string, ledger_credit: string, timestamp: string}
     */
    public function postFolioTransferToCityLedger(string $subFolioId): array
    {
        $subFolio = HtlSubFolio::findOrFail($subFolioId);

        if ($subFolio->payer_type !== 'CORPORATE' || $subFolio->corporate_id === null) {
            throw new InvalidArgumentException('Sub-folio bukan merupakan penagihan akun korporat yang valid.');
        }

        $amount = (int) $subFolio->balance_idr;
        if ($amount <= 0) {
            throw new InvalidArgumentException('Saldo sub-folio harus bernilai positif untuk dipindahkan ke City Ledger.');
        }

        $corp = HtlCorporateAccount::findOrFail($subFolio->corporate_id);
        $corp->chargeCredit($amount);

        $journalRef = 'JRN-CL-TRF-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        $debitAccount = 'htl:city_ledger:IDR';
        $creditAccount = "htl:guest_folio:{$subFolio->folio_id}:IDR";

        // Tandai sub-folio sebagai INVOICED ke City Ledger
        $subFolio->status = 'INVOICED';
        $subFolio->save();

        return [
            'journal_ref' => $journalRef,
            'corporate_id' => $corp->id,
            'transferred_amount_idr' => $amount,
            'ledger_debit' => $debitAccount,
            'ledger_credit' => $creditAccount,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Membukukan penerimaan pelunasan piutang korporat via transfer bank kliring.
     *
     * Debit: bank:clearing:IDR
     * Kredit: htl:city_ledger:IDR
     *
     * @return array{payment_ref: string, corporate_id: string, paid_amount_idr: int, remaining_outstanding_idr: int, timestamp: string}
     */
    public function recordCorporatePaymentReceipt(
        string $corporateAccountId,
        int $paymentAmountIdr,
        string $bankReference
    ): array {
        $corp = HtlCorporateAccount::findOrFail($corporateAccountId);

        if ($paymentAmountIdr <= 0) {
            throw new InvalidArgumentException('Jumlah pembayaran harus lebih besar dari nol.');
        }

        $corp->settleCredit($paymentAmountIdr);

        $paymentRef = 'RCP-CORP-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        return [
            'payment_ref' => $paymentRef,
            'corporate_id' => $corp->id,
            'paid_amount_idr' => $paymentAmountIdr,
            'remaining_outstanding_idr' => (int) $corp->current_outstanding_idr,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
