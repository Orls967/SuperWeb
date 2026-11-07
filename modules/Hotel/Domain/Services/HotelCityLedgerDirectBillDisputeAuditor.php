<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Domain\Models\HtlCorporateAccount;
use Modules\Hotel\Domain\Models\HtlSubFolio;

class HotelCityLedgerDirectBillDisputeAuditor
{
    /**
     * Menganalisis tingkat sengketa faktur penagihan langsung (Dispute Ratio) portofolio korporat.
     *
     * @return array{corporate_id: string, company_name: string, total_invoiced_sub_folios: int, disputed_sub_folios_count: int, dispute_rate_pct: float, corporate_reliability_status: string}
     */
    public function auditCorporateDisputeRate(string $corporateAccountId): array
    {
        $corp = HtlCorporateAccount::findOrFail($corporateAccountId);
        $subFolios = HtlSubFolio::where('corporate_id', $corporateAccountId)->get();

        $totalCount = $subFolios->count();
        $disputeCount = $subFolios->where('status', 'DISPUTED_HOLD')->count();

        $disputeRate = $totalCount > 0 ? round(($disputeCount / $totalCount) * 100, 2) : 0.0;

        $reliability = 'PRIME_RELIABLE';
        if ($disputeRate > 15.0) {
            $reliability = 'CHRONIC_DISPUTES_AUDIT_REQUIRED';
        } elseif ($disputeRate > 5.0) {
            $reliability = 'MODERATE_DISPUTES_MONITOR';
        }

        return [
            'corporate_id' => $corp->id,
            'company_name' => $corp->company_name,
            'total_invoiced_sub_folios' => $totalCount,
            'disputed_sub_folios_count' => $disputeCount,
            'dispute_rate_pct' => $disputeRate,
            'corporate_reliability_status' => $reliability,
        ];
    }
}
