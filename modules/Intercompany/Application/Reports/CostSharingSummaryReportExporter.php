<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Reports;

use Modules\Intercompany\Domain\Models\CostSharingAgreement;

/**
 * Exporter Laporan Perjanjian Pembagian Biaya (Cost Sharing Agreement / CSA) & Alokasi Jasa Manajemen.
 * Memuat rincian pool biaya bersama (IT, Legal, HR, Shared Services), basis alokasi proporsional (permil),
 * tagihan recovery per entitas anak, dan pemenuhan dokumentasi OECD Transfer Pricing.
 */
final class CostSharingSummaryReportExporter
{
    /**
     * @return array{filename: string, content: string, row_count: int, sha256: string}
     */
    public function export(string $period): array
    {
        $agreements = CostSharingAgreement::with('allocations')->get();

        $rows = [];
        $rows[] = [
            'Period',
            'Agreement Code',
            'Service Type',
            'Provider Entity',
            'Beneficiary Entity',
            'Allocation Basis',
            'Total Cost Pool IDR',
            'Beneficiary Ratio (permille)',
            'Allocated Cost IDR',
            'Markup (bps)',
            'Total Invoiced IDR',
            'Status',
            'Ledger Tx Ref',
        ];

        $totalCostPoolSum = 0;
        $totalAllocatedSum = 0;

        foreach ($agreements as $agr) {
            $totalCostPoolSum += (int) $agr->total_cost_pool_idr;

            foreach ($agr->allocations as $alloc) {
                if ($period !== '' && $alloc->period !== $period) {
                    continue;
                }

                $totalAllocatedSum += (int) $alloc->allocated_amount_idr;

                $rows[] = [
                    $alloc->period,
                    $agr->agreement_code,
                    $agr->service_type,
                    $agr->provider_entity,
                    $alloc->beneficiary_entity,
                    $alloc->allocation_basis ?? 'REVENUE_SHARE',
                    (string) $agr->total_cost_pool_idr,
                    (string) $alloc->basis_ratio_permille,
                    (string) $alloc->allocated_amount_idr,
                    (string) ($agr->markup_bps ?? 500),
                    (string) $alloc->allocated_amount_idr,
                    $alloc->status,
                    (string) ($alloc->ledger_transaction_id ?? '-'),
                ];
            }
        }

        $rows[] = [
            'SUMMARY',
            "Agreements: {$agreements->count()}",
            '',
            '',
            '',
            '',
            (string) $totalCostPoolSum,
            '100000',
            (string) $totalAllocatedSum,
            '',
            (string) $totalAllocatedSum,
            'ALLOCATED',
            '',
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "cost-sharing-summary-{$period}.csv",
            'content' => $csv,
            'row_count' => count($rows) - 2,
            'sha256' => hash('sha256', $csv),
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function buildCsv(array $rows): string
    {
        $fp = fopen('php://memory', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $content = stream_get_contents($fp);
        fclose($fp);

        return $content ?: '';
    }
}
