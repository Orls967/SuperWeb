<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Carbon\CarbonImmutable;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Services\HospitalLedgerPostingService;

final class ReconcileDailyInpatientBillingAction
{
    private AccrueDailyBedRevenueAction $accrueDailyBedRevenueAction;

    public function __construct(AccrueDailyBedRevenueAction $accrueDailyBedRevenueAction)
    {
        $this->accrueDailyBedRevenueAction = $accrueDailyBedRevenueAction;
    }

    /**
     * Menjalankan akrual massal sewa kamar ranjang harian untuk seluruh admisi aktif.
     *
     * @param string $serviceDate YYYY-MM-DD
     * @param int $actorId
     * @return array{
     *     service_date: string,
     *     total_active_admissions: int,
     *     total_posted_admissions: int,
     *     total_revenue_accrued_idr: int,
     *     skipped_admissions: list<int>
     * }
     */
    public function execute(string $serviceDate, int $actorId): array
    {
        $activeAdmissions = Admission::whereIn('status', ['ADMITTED', 'BED_OCCUPIED', 'ACTIVE'])
            ->with('bed')
            ->get();

        $totalPosted = 0;
        $totalRevenue = 0;
        $skipped = [];

        foreach ($activeAdmissions as $admission) {
            if (!$admission->bed) {
                $skipped[] = $admission->id;
                continue;
            }

            $rate = match (strtoupper($admission->bed->class)) {
                'ICU', 'NICU', 'PICU' => 2500000,
                'ISOLATION' => 1800000,
                'VVIP' => 3000000,
                'VIP' => 1500000,
                'CLASS_1' => 800000,
                'CLASS_2' => 500000,
                default => 300000,
            };

            $res = $this->accrueDailyBedRevenueAction->execute(
                admissionId: $admission->id,
                bedId: $admission->bed->id,
                dailyRateIdr: $rate,
                serviceDate: $serviceDate,
                actorId: $actorId
            );

            if ($res['success']) {
                $totalPosted++;
                $totalRevenue += $rate;
            } else {
                $skipped[] = $admission->id;
            }
        }

        return [
            'service_date' => $serviceDate,
            'total_active_admissions' => $activeAdmissions->count(),
            'total_posted_admissions' => $totalPosted,
            'total_revenue_accrued_idr' => $totalRevenue,
            'skipped_admissions' => $skipped,
        ];
    }
}
