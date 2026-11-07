<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;
use Modules\Hotel\Domain\Models\HtlSubFolio;

class HotelCityLedgerCreditSettlementDistributor
{
    /**
     * Mengalokasikan pembayaran transfer bank korporat secara FIFO ke sub-folio yang paling lama tertunggak (Aging Priority).
     *
     * @return array{corporate_id: string, total_payment_received_idr: int, fully_settled_sub_folios_count: int, partially_settled_sub_folios_count: int, unallocated_excess_idr: int}
     */
    public function allocatePaymentFifo(string $corporateAccountId, int $paymentAmountIdr): array
    {
        $corp = HtlCorporateAccount::findOrFail($corporateAccountId);

        if ($paymentAmountIdr <= 0) {
            throw new InvalidArgumentException('Nilai pembayaran pelunasan harus lebih dari nol.');
        }

        // Sub-folio terutang diurutkan dari yang paling lama
        $subFolios = HtlSubFolio::where('corporate_id', $corporateAccountId)
            ->where('status', '!=', 'SETTLED')
            ->where('balance_idr', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        $remainingPayment = $paymentAmountIdr;
        $fullySettledCount = 0;
        $partiallySettledCount = 0;

        foreach ($subFolios as $sf) {
            if ($remainingPayment <= 0) {
                break;
            }

            $due = (int) $sf->balance_idr;
            if ($remainingPayment >= $due) {
                $sf->recordPayment($due);
                $remainingPayment -= $due;
                $fullySettledCount++;
            } else {
                $sf->recordPayment($remainingPayment);
                $remainingPayment = 0;
                $partiallySettledCount++;
            }
        }

        // Potong outstanding di akun korporat
        $corp->settleCredit($paymentAmountIdr - $remainingPayment);

        return [
            'corporate_id' => $corp->id,
            'total_payment_received_idr' => $paymentAmountIdr,
            'fully_settled_sub_folios_count' => $fullySettledCount,
            'partially_settled_sub_folios_count' => $partiallySettledCount,
            'unallocated_excess_idr' => $remainingPayment,
        ];
    }
}
