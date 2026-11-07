<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlCorporateAccount;

class HotelCorporateCreditSanctionEnforcer
{
    /**
     * Memeriksa dan menetapkan sanksi penangguhan fasilitas direct billing bagi korporat wanprestasi.
     *
     * @return array{corporate_id: string, previous_status: string, new_status: string, sanction_reason: string, is_sanctioned: bool}
     */
    public function enforceCreditSanctions(
        string $corporateAccountId,
        int $daysOverdueMax,
        int $overdueAmountIdr
    ): array {
        $corp = HtlCorporateAccount::findOrFail($corporateAccountId);
        $prevStatus = (string) $corp->status;

        $newStatus = $prevStatus;
        $reason = 'NORMAL_OPERATIONS';
        $isSanctioned = false;

        if ($daysOverdueMax > 60 && $overdueAmountIdr > 10000000) {
            $newStatus = 'SUSPENDED';
            $reason = 'OVERDUE_EXCEEDED_60_DAYS';
            $isSanctioned = true;
        } elseif ($daysOverdueMax > 90) {
            $newStatus = 'TERMINATED_LEGAL_COLLECTION';
            $reason = 'DEFAULT_EXCEEDED_90_DAYS';
            $isSanctioned = true;
        }

        if ($newStatus !== $prevStatus) {
            $corp->status = $newStatus;
            $corp->save();
        }

        return [
            'corporate_id' => $corp->id,
            'previous_status' => $prevStatus,
            'new_status' => $newStatus,
            'sanction_reason' => $reason,
            'is_sanctioned' => $isSanctioned,
        ];
    }
}
