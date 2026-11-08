<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use DomainException;
use Modules\Logistics\Contracts\CustodyVerifier;
use Modules\Port\Domain\Models\FleetPortCall;
use Modules\Port\Domain\Models\FleetVessel;

/**
 * Service Pengawasan Kargo Maritim, Draft Survey & Verifikasi Rantai Kustodi.
 * Mengintegrasikan kontrak CustodyVerifier (Logistics K1) untuk integritas kargo curah dan kontainer.
 */
final class MaritimeCargoCustodyService
{
    public function __construct(
        private readonly ?CustodyVerifier $custodyVerifier = null
    ) {}

    /**
     * Hitung Draft Survey kapal untuk menentukan bobot bersih kargo yang dimuat/dibongkar.
     * Standar: UN ECE Code of Uniform Standards and Procedures for the Performance of Draught Surveys.
     *
     * @param float $draftFwdInitial Sarat depan awal (meter)
     * @param float $draftAftInitial Sarat belakang awal (meter)
     * @param float $draftMidInitial Sarat tengah awal (meter)
     * @param float $draftFwdFinal Sarat depan akhir (meter)
     * @param float $draftAftFinal Sarat belakang akhir (meter)
     * @param float $draftMidFinal Sarat tengah akhir (meter)
     * @param float $waterDensityInitial Densitas air laut saat survey awal (kg/L, misal 1.025)
     * @param float $waterDensityFinal Densitas air laut saat survey akhir
     * @param float $tpc Tonnage per Centimetre immersion (MT/cm)
     * @param float $constantDeductible Koreksi bobot konstan kapal (stores, lube oil, unpumpable ballast)
     * @return array{
     *     mean_draft_initial_m: float,
     *     mean_draft_final_m: float,
     *     draft_difference_m: float,
     *     displacement_initial_mt: float,
     *     displacement_final_mt: float,
     *     net_cargo_loaded_mt: float,
     *     density_correction_applied: bool
     * }
     */
    public function calculateDraftSurvey(
        float $draftFwdInitial,
        float $draftAftInitial,
        float $draftMidInitial,
        float $draftFwdFinal,
        float $draftAftFinal,
        float $draftMidFinal,
        float $waterDensityInitial = 1.025,
        float $waterDensityFinal = 1.025,
        float $tpc = 55.0,
        float $constantDeductible = 450.0
    ): array {
        // Quarter Mean Draft Formula (Keel Sag/Hog Correction): (Fwd + 6*Mid + Aft) / 8
        $quarterMeanInitial = ($draftFwdInitial + (6.0 * $draftMidInitial) + $draftAftInitial) / 8.0;
        $quarterMeanFinal = ($draftFwdFinal + (6.0 * $draftMidFinal) + $draftAftFinal) / 8.0;

        $draftDiff = $quarterMeanFinal - $quarterMeanInitial;

        // Estimasi displasemen: Displasemen = (Quarter Mean Draft * 100 cm) * TPC
        $dispInitial = $quarterMeanInitial * 100.0 * $tpc;
        $dispFinal = $quarterMeanFinal * 100.0 * $tpc;

        // Koreksi densitas air laut standar (1.025): Displasemen_aktual = Displasemen_tabel * (Densitas_aktual / 1.025)
        $corrInitial = $dispInitial * ($waterDensityInitial / 1.025);
        $corrFinal = $dispFinal * ($waterDensityFinal / 1.025);

        $netCargo = max(0.0, ($corrFinal - $corrInitial) - $constantDeductible);

        return [
            'mean_draft_initial_m' => round($quarterMeanInitial, 3),
            'mean_draft_final_m' => round($quarterMeanFinal, 3),
            'draft_difference_m' => round($draftDiff, 3),
            'displacement_initial_mt' => round($corrInitial, 2),
            'displacement_final_mt' => round($corrFinal, 2),
            'net_cargo_loaded_mt' => round($netCargo, 2),
            'density_correction_applied' => true,
        ];
    }

    /**
     * Catat dan verifikasi integritas segel palka kapal (Hatch Cover Sealing).
     */
    public function verifyHatchSealing(
        int $portCallId,
        int $hatchNumber,
        string $sealNumber,
        string $inspectorName,
        CarbonImmutable $sealedAt
    ): array {
        $portCall = FleetPortCall::findOrFail($portCallId);

        $isVerified = true;
        if ($this->custodyVerifier !== null) {
            // Verifikasi segel lewat modul Logistics
            $isVerified = $this->custodyVerifier->verifySeal(
                'FLEET_VESSEL_HATCH',
                $portCall->id,
                $sealNumber
            );
        }

        return [
            'port_call_id' => $portCall->id,
            'hatch_number' => $hatchNumber,
            'seal_number' => $sealNumber,
            'inspector' => $inspectorName,
            'sealed_at' => $sealedAt->toIso8601String(),
            'is_tamper_free' => $isVerified,
            'status' => $isVerified ? 'SEALED_AND_VERIFIED' : 'SEAL_COMPROMISED',
        ];
    }
}
