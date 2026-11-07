<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;
use Modules\Hotel\Domain\Models\HtlSubFolio;

class HotelFolioSplitReversalService
{
    /**
     * Membatalkan penyelesaian split folio dan mengembalikan saldo kredit korporat jika kasir salah membebankan.
     *
     * @return array{sub_folio_id: string, corporate_id: ?string, reversed_amount_idr: int, status: string, reversed_at: string}
     */
    public function reverseSubFolioSettlement(string $subFolioId, string $reversalReason): array
    {
        $subFolio = HtlSubFolio::findOrFail($subFolioId);

        if ($subFolio->status !== 'SETTLED' && $subFolio->status !== 'INVOICED') {
            throw new InvalidArgumentException('Hanya sub-folio yang telah berstatus SETTLED atau INVOICED yang dapat dibatalkan.');
        }

        $reversedAmount = (int) $subFolio->total_charges_idr;

        // Jika ada akun korporat yang dibebani, kurangi outstanding AR-nya
        if ($subFolio->corporate_id !== null) {
            $corp = HtlCorporateAccount::find($subFolio->corporate_id);
            if ($corp !== null) {
                $corp->settleCredit($reversedAmount);
            }
        }

        $subFolio->status = 'REVERSED';
        $subFolio->save();

        return [
            'sub_folio_id' => $subFolio->id,
            'corporate_id' => $subFolio->corporate_id,
            'reversed_amount_idr' => $reversedAmount,
            'status' => 'REVERSED_SUCCESS',
            'reversed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
