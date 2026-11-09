<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * BloodBankHemovigilanceService
 *
 * Mengelola keselamatan transfusi darah (Hemovigilance), rantai dingin darah (2°C - 6°C),
 * kecocokan serologis crossmatch (Mayor, Minor, Coombs Test), serta deteksi reaksi transfusi akut.
 * Memastikan setiap kantong darah tertelusur dari UTD PMI hingga resipien dan penagihan biaya pengolahan darah (BPPD).
 */
class BloodBankHemovigilanceService
{
    public const BLOOD_PRODUCT_PACKED_RED_CELLS = 'PACKED_RED_CELLS_PRC';

    public const BLOOD_PRODUCT_WHOLE_BLOOD = 'WHOLE_BLOOD_WB';

    public const BLOOD_PRODUCT_FRESH_FROZEN_PLASMA = 'FRESH_FROZEN_PLASMA_FFP';

    public const BLOOD_PRODUCT_THROMBOCYTE_CONCENTRATE = 'THROMBOCYTE_CONCENTRATE_TC';

    public const CROSSMATCH_COMPATIBLE = 'CROSSMATCH_COMPATIBLE';

    public const CROSSMATCH_INCOMPATIBLE = 'CROSSMATCH_INCOMPATIBLE';

    /**
     * Verifikasi kompatibilitas serologis dan rantai dingin sebelum pengeluaran kantong darah.
     *
     * @param  string  $bloodBagBarcode  Barcode unik kantong darah PMI
     * @param  string  $donorBloodGroup  Golongan darah & rhesus donor (misal: 'A_POSITIVE')
     * @param  string  $recipientBloodGroup  Golongan darah & rhesus pasien (misal: 'A_POSITIVE')
     * @param  string  $productType  Jenis komponen darah
     * @param  bool  $majorCrossmatchNegative  Apakah hasil uji silang mayor negatif (tidak aglutinasi)
     * @param  bool  $minorCrossmatchNegative  Apakah hasil uji silang minor negatif
     * @param  float  $storageTemperatureCelsius  Suhu kulkas bank darah saat pengeluaran
     * @param  DateTimeImmutable  $expiryDate  Tanggal kadaluarsa kantong darah
     * @param  DateTimeImmutable  $releaseTime  Waktu pengeluaran
     * @return array{
     *     blood_bag_barcode: string,
     *     is_safe_for_transfusion: bool,
     *     crossmatch_verdict: string,
     *     cold_chain_status: string,
     *     transfusion_audit_hash: string,
     *     contraindications: array<string>
     * }
     */
    public function verifyBloodRelease(
        string $bloodBagBarcode,
        string $donorBloodGroup,
        string $recipientBloodGroup,
        string $productType,
        bool $majorCrossmatchNegative,
        bool $minorCrossmatchNegative,
        float $storageTemperatureCelsius,
        DateTimeImmutable $expiryDate,
        DateTimeImmutable $releaseTime
    ): array {
        if (empty($bloodBagBarcode)) {
            throw new InvalidArgumentException('Barcode kantong darah tidak boleh kosong');
        }

        $contraindications = [];

        // 1. Validasi kecocokan crossmatch
        if (! $majorCrossmatchNegative) {
            $contraindications[] = 'Uji silang mayor POSITIF (terjadi aglutinasi/hemolisis antara eritrosit donor dan serum resipien). Risiko syok hemolitik fatal.';
        }
        if (! $minorCrossmatchNegative) {
            $contraindications[] = 'Uji silang minor POSITIF (antibodi plasma donor bereaksi terhadap sel darah merah pasien).';
        }

        // 2. Validasi rantai dingin berdasarkan jenis produk
        $coldChainStatus = 'TEMPERATURE_OPTIMAL';
        if ($productType === self::BLOOD_PRODUCT_THROMBOCYTE_CONCENTRATE) {
            // Trombosit disimpan pada suhu ruang terkontrol 20°C - 24°C dengan agitator
            if ($storageTemperatureCelsius < 20.0 || $storageTemperatureCelsius > 24.0) {
                $coldChainStatus = 'TEMPERATURE_BREACH';
                $contraindications[] = sprintf('Suhu penyimpanan konsentrat trombosit (%.1f°C) di luar rentang wajib (20.0 - 24.0°C).', $storageTemperatureCelsius);
            }
        } elseif ($productType === self::BLOOD_PRODUCT_FRESH_FROZEN_PLASMA) {
            // FFP disimpan beku <= -18°C
            if ($storageTemperatureCelsius > -18.0) {
                $coldChainStatus = 'TEMPERATURE_BREACH';
                $contraindications[] = sprintf('Suhu penyimpanan plasma beku FFP (%.1f°C) di atas batas maksimum (-18.0°C).', $storageTemperatureCelsius);
            }
        } else {
            // PRC dan Whole Blood disimpan pada 2°C - 6°C
            if ($storageTemperatureCelsius < 2.0 || $storageTemperatureCelsius > 6.0) {
                $coldChainStatus = 'TEMPERATURE_BREACH';
                $contraindications[] = sprintf('Suhu penyimpanan kulkas darah (%.1f°C) di luar rentang standar (2.0 - 6.0°C).', $storageTemperatureCelsius);
            }
        }

        // 3. Validasi kedaluwarsa
        if ($releaseTime > $expiryDate) {
            $contraindications[] = 'Kantong darah telah melewati masa simpan kedaluwarsa.';
        }

        $isSafe = empty($contraindications);
        $crossmatchVerdict = ($majorCrossmatchNegative && $minorCrossmatchNegative)
            ? self::CROSSMATCH_COMPATIBLE
            : self::CROSSMATCH_INCOMPATIBLE;

        $hashPayload = sprintf('%s|%s|%s|%s|%s', $bloodBagBarcode, $donorBloodGroup, $recipientBloodGroup, $productType, $releaseTime->format('Y-m-d H:i:s'));
        $auditHash = 'HEMOVIGILANCE-'.strtoupper(hash('sha256', $hashPayload));

        return [
            'blood_bag_barcode' => $bloodBagBarcode,
            'is_safe_for_transfusion' => $isSafe,
            'crossmatch_verdict' => $crossmatchVerdict,
            'cold_chain_status' => $coldChainStatus,
            'transfusion_audit_hash' => $auditHash,
            'contraindications' => $contraindications,
        ];
    }
}
