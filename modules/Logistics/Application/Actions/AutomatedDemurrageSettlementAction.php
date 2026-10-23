<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Modules\Shared\Application\BaseAction;

class AutomatedDemurrageSettlementAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        $containerNumber = (string) $params['container_number'];
        $dwellDays = (int) $params['dwell_days'];
        $freeDays = (int) ($params['free_days_allowed'] ?? 5);
        $dailyRateIdr = (int) ($params['daily_demurrage_rate_idr'] ?? 750000);
        $hubFacility = (string) ($params['hub_facility'] ?? 'Tanjung Priok Container Terminal 1');

        $billableDays = max(0, $dwellDays - $freeDays);
        $totalDemurrageFeeIdr = $billableDays * $dailyRateIdr;
        $status = $billableDays > 0 ? 'billable_assessed' : 'exempt_within_free_window';

        $assessmentReference = 'DMR-'.strtoupper(hash('crc32b', "{$containerNumber}:{$dwellDays}:{$totalDemurrageFeeIdr}"));

        $result = [
            'assessment_reference' => $assessmentReference,
            'container_number' => $containerNumber,
            'hub_facility' => $hubFacility,
            'total_dwell_days' => $dwellDays,
            'free_days_allowed' => $freeDays,
            'billable_demurrage_days' => $billableDays,
            'daily_demurrage_rate_idr' => $dailyRateIdr,
            'total_demurrage_fee_idr' => $totalDemurrageFeeIdr,
            'status' => $status,
        ];

        $this->audit(
            action: 'automated_demurrage_settled',
            newValues: [
                'container' => $containerNumber,
                'billable_days' => $billableDays,
                'fee_idr' => $totalDemurrageFeeIdr,
            ],
            impactType: 'port_demurrage_settlement',
        );

        return $result;
    }
}
