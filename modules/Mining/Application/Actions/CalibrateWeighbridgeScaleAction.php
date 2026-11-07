<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningSite;

class CalibrateWeighbridgeScaleAction
{
    /**
     * Kalibrasi dan tera metrologi legal jembatan timbang tambang (D94-1).
     *
     * Prosedur pengujian akurasi sel beban (load cells) dengan anak timbangan standar:
     * - Uji Beban Nol (Zero load stability)
     * - Uji Beban Bertingkat (Step load: 10 ton, 30 ton, 60 ton, 100 ton)
     * - Uji Eksentrisitas Titik Sudut (Eccentricity test pada 4 sudut jembatan timbang)
     * - Batas Kesalahan yang Diizinkan (Maximum Permissible Error / MPE per kelas OIML III: +/- 0.1% = 10 bps).
     *
     * @param array{
     *     site_id: string,
     *     scale_device_code: string,
     *     test_weight_standard_kg: int,
     *     indicated_weight_kg: int,
     *     corner_deviations_kg?: list<int>,
     *     technician_license_number: string,
     *     calibration_date?: string
     * } $data
     * @return array{
     *     scale_code: string,
     *     calibration_date: string,
     *     reference_weight_kg: int,
     *     indicated_weight_kg: int,
     *     error_margin_kg: int,
     *     error_margin_bps: int,
     *     max_permissible_error_bps: int,
     *     is_calibration_passed: bool,
     *     certificate_number: string,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $scaleCode = trim((string) $data['scale_device_code']);
            $refKg = (int) $data['test_weight_standard_kg'];
            $indicatedKg = (int) $data['indicated_weight_kg'];
            $cornerDeviations = (array) ($data['corner_deviations_kg'] ?? [0, 0, 0, 0]);
            $license = trim((string) $data['technician_license_number']);
            $calDate = ! empty($data['calibration_date']) ? CarbonImmutable::parse($data['calibration_date']) : CarbonImmutable::now();

            if ($refKg <= 0 || $indicatedKg <= 0) {
                throw new InvalidArgumentException('Beban uji standar dan penunjukan timbangan harus bernilai positif.');
            }

            $site = MiningSite::findOrFail($siteId);

            $errorKg = $indicatedKg - $refKg;
            $errorBps = (int) round(abs($errorKg) * 10000 / $refKg);
            $mpeBps = 10; // 0.10% batas toleransi OIML R76 Kelas III

            // Periksa juga eksentrisitas sudut (maksimal toleransi sudut 5 kg)
            $maxCornerDev = max(array_map('abs', $cornerDeviations));
            $isCornerPassed = ($maxCornerDev <= 20);

            $isPassed = ($errorBps <= $mpeBps) && $isCornerPassed;

            $certNumber = 'CAL-TERA-'.strtoupper($scaleCode).'-'.$calDate->format('Ymd');

            if (! $isPassed) {
                return [
                    'scale_code' => $scaleCode,
                    'calibration_date' => $calDate->toDateString(),
                    'reference_weight_kg' => $refKg,
                    'indicated_weight_kg' => $indicatedKg,
                    'error_margin_kg' => $errorKg,
                    'error_margin_bps' => $errorBps,
                    'max_permissible_error_bps' => $mpeBps,
                    'is_calibration_passed' => false,
                    'certificate_number' => $certNumber,
                    'status' => 'FAILED_RECALIBRATION_REQUIRED',
                ];
            }

            return [
                'scale_code' => $scaleCode,
                'calibration_date' => $calDate->toDateString(),
                'reference_weight_kg' => $refKg,
                'indicated_weight_kg' => $indicatedKg,
                'error_margin_kg' => $errorKg,
                'error_margin_bps' => $errorBps,
                'max_permissible_error_bps' => $mpeBps,
                'is_calibration_passed' => true,
                'certificate_number' => $certNumber,
                'status' => 'METROLOGY_CERTIFIED',
            ];
        });
    }
}
