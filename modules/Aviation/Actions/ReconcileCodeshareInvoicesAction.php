<?php

declare(strict_types=1);

namespace Modules\Aviation\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Aviation\Application\Services\AviationInterlineClearingHouseService;
use Modules\Aviation\Domain\Models\AviationCodeshareAgreement;

class ReconcileCodeshareInvoicesAction
{
    public function __construct(
        protected AviationInterlineClearingHouseService $clearingService
    ) {}

    /**
     * Jalankan rekonsiliasi faktur kliring codeshare bulanan antar-maskapai.
     *
     * @return array{agreement_id: int, billing_month: string, net_settlement_idr: int, status: string}
     */
    public function execute(int $agreementId, string $billingMonth): array
    {
        return DB::transaction(function () use ($agreementId, $billingMonth) {
            $agreement = AviationCodeshareAgreement::lockForUpdate()->findOrFail($agreementId);

            if (! $agreement->isActive()) {
                throw new DomainException(sprintf('Perjanjian codeshare [%s] tidak aktif.', $agreement->agreement_code));
            }

            $summary = $this->clearingService->generateMonthlyClearingSummary($agreementId, $billingMonth);

            $netIdr = (int) round($summary['net_settlement_minor_unit'] / 100);

            return [
                'agreement_id' => $agreement->id,
                'billing_month' => $billingMonth,
                'net_settlement_idr' => $netIdr,
                'status' => 'RECONCILED_SUCCESSFULLY',
            ];
        });
    }
}
