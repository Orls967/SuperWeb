<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

/**
 * Simulator Data Fixture Pajak e-Faktur & e-Bupot (54.3 - IZIN-5).
 * Menghasilkan berkas CSV berlabel simulasi/fixture untuk pengujian rekonsiliasi masa PPN
 * dan bukti potong PPh Pasal 23/PPh 4(2) tanpa koneksi ke server DJP nyata.
 */
class TaxFixtureDataSimulator
{
    /**
     * Menghasilkan berkas CSV simulasi e-Faktur Pajak Keluaran (Faktur Penjualan).
     *
     * @param  list<array{invoice_number: string, buyer_name: string, tax_base_idr: int, vat_rate_pct: int}>  $invoices
     * @return array{filename: string, content: string, total_vat_idr: int, sha256: string}
     */
    public function generateEfakturFixture(string $taxPeriod, array $invoices): array
    {
        $rows = [];
        $rows[] = [
            'FIXTURE_TAG: SIMULASI_DJP_INTERNAL_TEST_ONLY',
            'TAX_PERIOD: '.$taxPeriod,
            '',
            '',
            '',
        ];
        $rows[] = [
            'Invoice Number',
            'Buyer Name',
            'Tax Base (DPP) IDR',
            'VAT Rate (%)',
            'Output VAT (PPN) IDR',
        ];

        $totalVat = 0;
        foreach ($invoices as $inv) {
            $dpp = (int) $inv['tax_base_idr'];
            $rate = (int) ($inv['vat_rate_pct'] ?? 11);
            $vat = (int) intdiv($dpp * $rate, 100);
            $totalVat += $vat;

            $rows[] = [
                $inv['invoice_number'],
                $inv['buyer_name'],
                (string) $dpp,
                (string) $rate,
                (string) $vat,
            ];
        }

        $rows[] = [
            'TOTAL SUMMARY',
            'Count: '.count($invoices),
            '',
            'Total PPN Terutang',
            (string) $totalVat,
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "fixture-efaktur-ppn-output-{$taxPeriod}.csv",
            'content' => $csv,
            'total_vat_idr' => $totalVat,
            'sha256' => hash('sha256', $csv),
        ];
    }

    /**
     * Menghasilkan berkas CSV simulasi e-Bupot Unifikasi (PPh Pasal 23 & PPh Pasal 4 ayat 2).
     *
     * @param  list<array{doc_number: string, counterparty: string, tax_article: string, object_income_idr: int, rate_bps: int}>  $withholdings
     * @return array{filename: string, content: string, total_withheld_idr: int, sha256: string}
     */
    public function generateEbupotFixture(string $taxPeriod, array $withholdings): array
    {
        $rows = [];
        $rows[] = [
            'FIXTURE_TAG: SIMULASI_EBUPOT_INTERNAL_TEST_ONLY',
            'TAX_PERIOD: '.$taxPeriod,
            '',
            '',
            '',
        ];
        $rows[] = [
            'Withholding Doc Number',
            'Counterparty Vendor',
            'Tax Article',
            'Gross Income IDR',
            'Tax Rate (BPS)',
            'Tax Withheld IDR',
        ];

        $totalWithheld = 0;
        foreach ($withholdings as $w) {
            $gross = (int) $w['object_income_idr'];
            $bps = (int) $w['rate_bps'];
            $tax = (int) intdiv($gross * $bps, 10000);
            $totalWithheld += $tax;

            $rows[] = [
                $w['doc_number'],
                $w['counterparty'],
                $w['tax_article'],
                (string) $gross,
                (string) $bps,
                (string) $tax,
            ];
        }

        $rows[] = [
            'TOTAL SUMMARY',
            'Count: '.count($withholdings),
            '',
            '',
            'Total PPh Dipotong',
            (string) $totalWithheld,
        ];

        $csv = $this->buildCsv($rows);

        return [
            'filename' => "fixture-ebupot-unifikasi-{$taxPeriod}.csv",
            'content' => $csv,
            'total_withheld_idr' => $totalWithheld,
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
