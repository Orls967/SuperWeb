<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\EnterpriseFinance\Domain\Algorithms\Reconciliation\SubledgerReconciler;
use Modules\EnterpriseFinance\Domain\Algorithms\Tax\ProgressiveTaxCalculator;
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
     * Menghitung PPh Pasal 21 Karyawan Menggunakan Metode TER PP 58/2023.
     *
     * @param  int  $grossSalaryMinor  Gaji kotor bulanan dalam minor IDR.
     * @param  string  $ptkpCode  Kode status keluarga (TK/0, K/1, dll).
     * @param  bool  $hasNpwp  Apakah memiliki NPWP valid.
     * @return array{
     *     gross_salary_minor: int,
     *     ptkp_code: string,
     *     ter_category: string,
     *     ter_rate_bps: int,
     *     tax_withheld_minor: int,
     *     take_home_pay_minor: int
     * }
     */
    public function calculatePph21MonthlyTer(
        int $grossSalaryMinor,
        string $ptkpCode,
        bool $hasNpwp = true
    ): array {
        if ($grossSalaryMinor < 0) {
            throw new InvalidArgumentException('Penghasilan bruto tidak boleh negatif.');
        }

        $terCategory = ProgressiveTaxCalculator::resolveTerCategory($ptkpCode);
        $terRateBps = ProgressiveTaxCalculator::lookupTerMonthlyRateBps($terCategory, $grossSalaryMinor);

        $taxWithheld = intdiv($grossSalaryMinor * $terRateBps, 10000);
        if (! $hasNpwp) {
            $taxWithheld = intdiv($taxWithheld * 120, 100); // Penalti 20% tanpa NPWP
        }

        $takeHomePay = $grossSalaryMinor - $taxWithheld;

        return [
            'gross_salary_minor' => $grossSalaryMinor,
            'ptkp_code' => $ptkpCode,
            'ter_category' => $terCategory,
            'ter_rate_bps' => $terRateBps,
            'tax_withheld_minor' => $taxWithheld,
            'take_home_pay_minor' => $takeHomePay,
        ];
    }

    /**
     * Menghitung Pemotongan PPh Pasal 23 atas Pengadaan Jasa dan Sewa Peralatan.
     *
     * @param  int  $dppMinor  Dasar Pengenaan Pajak dalam minor IDR.
     * @param  string  $serviceType  Jenis jasa ('MANAGEMENT', 'TECHNICAL', 'CONSULTING', 'EQUIPMENT_RENTAL', 'ROYALTY')
     * @return array{
     *     dpp_minor: int,
     *     service_type: string,
     *     rate_bps: int,
     *     tax_withheld_minor: int,
     *     net_payable_to_vendor_minor: int
     * }
     */
    public function calculatePph23Withholding(
        int $dppMinor,
        string $serviceType,
        bool $hasNpwp = true
    ): array {
        if ($dppMinor <= 0) {
            throw new InvalidArgumentException('DPP jasa wajib lebih besar dari nol.');
        }

        $baseRateBps = match (strtoupper($serviceType)) {
            'ROYALTY', 'DIVIDEND' => 1500, // 15%
            'MANAGEMENT', 'TECHNICAL', 'CONSULTING', 'EQUIPMENT_RENTAL' => 200, // 2%
            default => 200,
        };

        $effectiveRateBps = $hasNpwp ? $baseRateBps : ($baseRateBps * 2); // 100% lebih tinggi tanpa NPWP
        $taxWithheld = intdiv($dppMinor * $effectiveRateBps, 10000);
        $netPayable = $dppMinor - $taxWithheld;

        return [
            'dpp_minor' => $dppMinor,
            'service_type' => $serviceType,
            'rate_bps' => $effectiveRateBps,
            'tax_withheld_minor' => $taxWithheld,
            'net_payable_to_vendor_minor' => $netPayable,
        ];
    }

    /**
     * Menghitung PPh Pasal 4 ayat (2) Final atas Sewa Properti, Tanah, dan Bangunan.
     *
     * @param  int  $dppMinor  Nilai sewa bruto.
     * @return array{
     *     dpp_minor: int,
     *     rate_bps: int,
     *     tax_final_minor: int,
     *     net_rent_payable_minor: int
     * }
     */
    public function calculatePph4Ayat2Final(int $dppMinor): array
    {
        if ($dppMinor <= 0) {
            throw new InvalidArgumentException('Nilai sewa properti harus lebih besar dari nol.');
        }

        $rateBps = 1000; // 10.00% final
        $taxFinal = intdiv($dppMinor * $rateBps, 10000);
        $netRentPayable = $dppMinor - $taxFinal;

        return [
            'dpp_minor' => $dppMinor,
            'rate_bps' => $rateBps,
            'tax_final_minor' => $taxFinal,
            'net_rent_payable_minor' => $netRentPayable,
        ];
    }

    /**
     * Memvalidasi ketiadaan celah (*gapless sequence*) pada sekumpulan nomor faktur pajak elektronik.
     *
     * @param  list<string>  $invoiceNumbers
     * @return array{
     *     is_valid_gapless: bool,
     *     details: array<string, mixed>
     * }
     */
    public function validateTaxInvoiceSequence(array $invoiceNumbers, string $expectedPrefix): array
    {
        $recon = SubledgerReconciler::verifyGaplessSequence($invoiceNumbers, $expectedPrefix);

        return [
            'is_valid_gapless' => $recon['is_gapless'],
            'details' => $recon,
        ];
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
            ['01', '0', '010.001-26.00000003', '10', '2026', '2026-10-25', '03.456.789.0-034.000', 'CV Logistik Prima Jaya', 'Jl. Pemuda No 12 Semarang', '80000000', '8800000', 'fixture'],
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
