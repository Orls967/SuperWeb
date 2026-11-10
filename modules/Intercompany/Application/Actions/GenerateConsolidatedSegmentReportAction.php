<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use Modules\Intercompany\Domain\DTOs\SegmentFinancialsDTO;

class GenerateConsolidatedSegmentReportAction
{
    /**
     * @return array{
     *     period: string,
     *     segments: array<int, SegmentFinancialsDTO>,
     *     total_external_revenue_idr: int,
     *     total_inter_segment_revenue_idr: int,
     *     total_elimination_idr: int,
     *     consolidated_group_revenue_idr: int
     * }
     */
    public function execute(string $period): array
    {
        $segments = [
            new SegmentFinancialsDTO('SEG_MFG', 'Manufaktur & Energi', 45_000_000, 15_000_000, 60_000_000, 35_000_000, 25_000_000, 150_000_000, 50_000_000),
            new SegmentFinancialsDTO('SEG_LOG', 'Logistik & Pelabuhan', 20_000_000, 8_000_000, 28_000_000, 16_000_000, 12_000_000, 80_000_000, 30_000_000),
            new SegmentFinancialsDTO('SEG_RTL', 'Retail & Store Niaga', 60_000_000, 2_000_000, 62_000_000, 48_000_000, 14_000_000, 95_000_000, 40_000_000),
            new SegmentFinancialsDTO('SEG_RST', 'Restoran & Cloud Kitchen', 18_000_000, 1_000_000, 19_000_000, 12_000_000, 7_000_000, 35_000_000, 15_000_000),
            new SegmentFinancialsDTO('SEG_PRP', 'Properti & Mall', 30_000_000, 4_000_000, 34_000_000, 15_000_000, 19_000_000, 200_000_000, 70_000_000),
        ];

        $totalExt = 0;
        $totalInter = 0;
        foreach ($segments as $s) {
            $totalExt += $s->externalRevenueIdr;
            $totalInter += $s->interSegmentRevenueIdr;
        }

        $elimination = -$totalInter;
        $groupRevenue = $totalExt;

        return [
            'period' => $period,
            'segments' => $segments,
            'total_external_revenue_idr' => $totalExt,
            'total_inter_segment_revenue_idr' => $totalInter,
            'total_elimination_idr' => $elimination,
            'consolidated_group_revenue_idr' => $groupRevenue,
        ];
    }
}
