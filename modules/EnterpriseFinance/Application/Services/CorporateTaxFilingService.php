<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;

/**
 * Layanan Kompilasi dan Pembuatan Draf Formulir SPT Masa PPN 1111 & e-Bupot Unifikasi Nasional.
 * Memetakan seluruh transaksi kena pajak ke dalam lampiran resmi Direktorat Jenderal Pajak (DJP).
 */
class CorporateTaxFilingService
{
    /**
     * Membentuk Struktur Draf Lengkap SPT Masa PPN 1111.
     *
     * @param string $entityCode
     * @param string $period YYYY-MM
     * @param int $exportTaxBaseMinor Dasar Pengenaan Pajak Ekspor BKP/JKP (Tarif 0%).
     * @param int $domesticTaxBaseMinor Penyerahan BKP/JKP Dalam Negeri Terutang PPN (11%).
     * @param int $exemptTaxBaseMinor Penyerahan yang PPN-nya Tidak Dipungut / Dibebaskan.
     * @param int $domesticInputTaxMinor PPN Masukan yang Dapat Dikreditkan.
     * @param int $importedGoodsInputTaxMinor PPN Masukan atas Impor BKP.
     * @return array{
     *     header: array<string, string>,
     *     form_1111_induk: array<string, int>,
     *     form_1111_ab: array<string, int>,
     *     settlement: array<string, int|string>
     * }
     */
    public function generateSptMasaPpn1111(
        string $entityCode,
        string $period,
        int $exportTaxBaseMinor,
        int $domesticTaxBaseMinor,
        int $exemptTaxBaseMinor,
        int $domesticInputTaxMinor,
        int $importedGoodsInputTaxMinor
    ): array {
        if ($exportTaxBaseMinor < 0 || $domesticTaxBaseMinor < 0 || $domesticInputTaxMinor < 0) {
            throw new InvalidArgumentException('Nilai DPP dan PPN tidak boleh negatif.');
        }

        // 1. Perhitungan PPN Keluaran
        $outputTaxDomestic = intdiv($domesticTaxBaseMinor * 1100, 10000); // 11.00%
        $totalOutputTax = $outputTaxDomestic; // Ekspor tarif 0%

        // 2. Perhitungan PPN Masukan Dapat Dikreditkan
        $totalCreditableInputTax = $domesticInputTaxMinor + $importedGoodsInputTaxMinor;

        // 3. Rekapitulasi Kurang / Lebih Bayar
        $netVat = $totalOutputTax - $totalCreditableInputTax;
        $status = match (true) {
            $netVat > 0 => 'KURANG_BAYAR',
            $netVat < 0 => 'LEBIH_BAYAR',
            default => 'NIHIL',
        };

        return [
            'header' => [
                'entity_code' => $entityCode,
                'period' => $period,
                'tax_year' => substr($period, 0, 4),
                'tax_month' => substr($period, 5, 2),
                'spt_type' => 'SPT_MASA_PPN_1111_NORMAL',
            ],
            'form_1111_induk' => [
                'total_penyerahan_bruto_minor' => $exportTaxBaseMinor + $domesticTaxBaseMinor + $exemptTaxBaseMinor,
                'penyerahan_terutang_ppn_minor' => $domesticTaxBaseMinor,
                'penyerahan_ekspor_0_pct_minor' => $exportTaxBaseMinor,
                'penyerahan_tidak_dipungut_minor' => $exemptTaxBaseMinor,
                'total_ppn_keluaran_minor' => $totalOutputTax,
                'total_ppn_masukan_minor' => $totalCreditableInputTax,
                'ppn_kurang_lebih_bayar_minor' => $netVat,
            ],
            'form_1111_ab' => [
                'ekspor_bkp_minor' => $exportTaxBaseMinor,
                'penyerahan_dalam_negeri_dpp_minor' => $domesticTaxBaseMinor,
                'penyerahan_dalam_negeri_ppn_minor' => $outputTaxDomestic,
                'perolehan_bkp_dalam_negeri_minor' => $domesticInputTaxMinor,
                'impor_bkp_luar_negeri_minor' => $importedGoodsInputTaxMinor,
            ],
            'settlement' => [
                'status' => $status,
                'payable_amount_minor' => max(0, $netVat),
                'refundable_amount_minor' => max(0, -$netVat),
            ],
        ];
    }

    /**
     * Menghasilkan Struktur Dokumen XML Bukti Potong Elektronik (e-Bupot Unifikasi DJP).
     *
     * @param string $bupotNumber Nomor bukti potong unik.
     * @param string $entityNpwp NPWP pemotong pajak.
     * @param string $counterpartyNpwp NPWP rekanan / wajib pajak dipotong.
     * @param string $taxArticle Pasal pajak ('21', '22', '23', '4_2').
     * @param int $dppMinor Nilai DPP transaksi.
     * @param int $taxWithheldMinor Nilai pajak dipotong.
     * @return string Dokumen XML siap unggah ke portal e-Bupot DJP.
     */
    public function generateEBupotXmlDocument(
        string $bupotNumber,
        string $entityNpwp,
        string $counterpartyNpwp,
        string $taxArticle,
        int $dppMinor,
        int $taxWithheldMinor
    ): string {
        $cleanEntityNpwp = str_replace(['.', '-'], '', $entityNpwp);
        $cleanCounterpartyNpwp = str_replace(['.', '-'], '', $counterpartyNpwp);
        $dppRupiah = intdiv($dppMinor, 100);
        $taxRupiah = intdiv($taxWithheldMinor, 100);

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
            "<EBupotUnifikasiDocument xmlns=\"http://djp.pajak.go.id/ebupot\">\n" .
            "  <Header>\n" .
            "    <NoBupot>{$bupotNumber}</NoBupot>\n" .
            "    <NpwpPemotong>{$cleanEntityNpwp}</NpwpPemotong>\n" .
            "    <NpwpWpDipungut>{$cleanCounterpartyNpwp}</NpwpWpDipungut>\n" .
            "    <PasalPajak>PASAL_{$taxArticle}</PasalPajak>\n" .
            "  </Header>\n" .
            "  <Detail>\n" .
            "    <JumlahDpp>{$dppRupiah}</JumlahDpp>\n" .
            "    <JumlahPphDipungut>{$taxRupiah}</JumlahPphDipungut>\n" .
            "    <StatusOtorisasi>AUTHORIZED</StatusOtorisasi>\n" .
            "  </Detail>\n" .
            "</EBupotUnifikasiDocument>\n";
    }
}

