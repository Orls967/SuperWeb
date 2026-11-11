<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\EnterpriseFinance\Domain\Models\TaxReconSummary;
use Psr\Clock\ClockInterface;

class CorporateTaxReconciliationService
{
    public function __construct(
        protected readonly EnterpriseFinanceLedgerPoster $ledgerPoster,
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Merekonsiliasi PPN Masukan vs Keluaran dan menghasilkan nomor faktur gapless.
     *
     * @return array{
     *     entity: string,
     *     period: string,
     *     vat_output_minor: int,
     *     vat_input_minor: int,
     *     net_payable_minor: int,
     *     spt_draft_number: string,
     *     status: string
     * }
     */
    public function reconcileVat(
        string $entityCode,
        string $period,
        int $vatOutputMinor,
        int $vatInputMinor
    ): array {
        $netPayableMinor = max(0, $vatOutputMinor - $vatInputMinor);

        // Nomor seri faktur gapless simulasi (misal: 010.001-26.00000001)
        $cleanPeriod = str_replace('-', '', $period);
        $seqCount = TaxReconSummary::query()->count() + 1;
        $gaplessDraftNumber = sprintf('010.001-%02d.%08d', (int) substr($cleanPeriod, 2, 2), $seqCount);

        $summary = TaxReconSummary::updateOrCreate(
            [
                'entity_code' => $entityCode,
                'period' => $period,
            ],
            [
                'vat_output_minor' => $vatOutputMinor,
                'vat_input_minor' => $vatInputMinor,
                'net_payable_minor' => $netPayableMinor,
                'settlement_status' => 'RECONCILED',
                'spt_draft_number' => $gaplessDraftNumber,
            ]
        );

        return [
            'entity' => $entityCode,
            'period' => $period,
            'vat_output_minor' => $vatOutputMinor,
            'vat_input_minor' => $vatInputMinor,
            'net_payable_minor' => $netPayableMinor,
            'spt_draft_number' => $gaplessDraftNumber,
            'status' => 'RECONCILED',
        ];
    }

    /**
     * Mengeksekusi setoran PPN kurang bayar ke Kas Negara dan menjurnal buku besar.
     */
    public function settleVat(
        string $entityCode,
        string $period,
        int $operatorId
    ): TaxReconSummary {
        /** @var TaxReconSummary $summary */
        $summary = TaxReconSummary::query()
            ->where('entity_code', $entityCode)
            ->where('period', $period)
            ->firstOrFail();

        if ($summary->settlement_status === 'SETTLED') {
            return $summary;
        }

        DB::transaction(function () use ($summary, $entityCode, $period, $operatorId): void {
            if ($summary->net_payable_minor > 0) {
                $this->ledgerPoster->postVatSettlement(
                    entity: $entityCode,
                    period: $period,
                    vatOutputMinor: (int) $summary->vat_output_minor,
                    vatInputMinor: (int) $summary->vat_input_minor,
                    idempotencyKey: "IDEM:EF:VAT_SETTLE:{$entityCode}:{$period}",
                    userId: $operatorId
                );
            }

            $summary->settlement_status = 'SETTLED';
            $summary->save();
        });

        return $summary;
    }

    /**
     * Menghasilkan ekspor CSV simulasi e-Faktur dan e-Bupot berlabel fixture.
     */
    public function generateTaxCsvSimulation(string $entityCode, string $period): string
    {
        $rows = [
            ['KD_JENIS_TRANSAKSI', 'FG_PENGGANTI', 'NOMOR_FAKTUR', 'MASA_PAJAK', 'TAHUN_PAJAK', 'TANGGAL_FAKTUR', 'NPWP', 'NAMA', 'ALAMAT_LENGKAP', 'JUMLAH_DPP', 'JUMLAH_PPN', 'LABEL'],
            ['01', '0', '010.001-26.00000001', '10', '2026', '2026-10-15', '01.234.567.8-012.000', 'PT Mitra Niaga Corp', 'Jl. Sudirman Kav 20 Jakarta', '100000000', '11000000', 'fixture'],
            ['01', '0', '010.001-26.00000002', '10', '2026', '2026-10-20', '02.345.678.9-023.000', 'PT Mega Distribusi Utama', 'Jl. Gatot Subroto No 5 Bandung', '150000000', '16500000', 'fixture'],
        ];

        $fp = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return (string) $csv;
    }
}

