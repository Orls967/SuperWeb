<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Hotel\Domain\Models\HtlCorporateAccount;

class HotelCorporateCreditLineAdjustmentAction
{
    /**
     * Menyesuaikan pagu kredit akun korporat secara dinamis berdasarkan penilaian komite kredit perhotelan.
     *
     * @return array{corporate_id: string, previous_credit_limit_idr: int, new_credit_limit_idr: int, adjustment_reason: string, approved_by_role: string, adjusted_at: string}
     */
    public function adjustCreditLimit(
        string $corporateAccountId,
        int $newCreditLimitIdr,
        string $adjustmentReason,
        string $approverRole = 'FINANCIAL_CONTROLLER'
    ): array {
        $corp = HtlCorporateAccount::findOrFail($corporateAccountId);
        $prevLimit = (int) $corp->credit_limit_idr;

        if ($newCreditLimitIdr < 0) {
            throw new InvalidArgumentException('Plafon kredit baru tidak boleh negatif.');
        }

        // Peningkatan di atas Rp 250 Juta wajib persetujuan General Manager
        if ($newCreditLimitIdr > 250000000 && ! in_array($approverRole, ['GENERAL_MANAGER', 'HOTEL_GM', 'ADMIN'], true)) {
            throw new InvalidArgumentException('Plafon kredit melampaui Rp 250.000.000 mewajibkan otorisasi tingkat General Manager.');
        }

        $corp->credit_limit_idr = $newCreditLimitIdr;
        $corp->save();

        return [
            'corporate_id' => $corp->id,
            'previous_credit_limit_idr' => $prevLimit,
            'new_credit_limit_idr' => $newCreditLimitIdr,
            'adjustment_reason' => $adjustmentReason,
            'approved_by_role' => $approverRole,
            'adjusted_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
