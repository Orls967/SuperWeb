<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Esg\Contracts\EmissionBenefitRecorder;
use Modules\Port\Domain\Models\FleetVessel;
use Modules\Shared\Domain\Algorithms\Port\CiiCarbonIntensityRatingEngine;

/**
 * Service Audit Emisi Maritim, Rating CII IMO & Pelaporan ESG.
 * Terintegrasi dengan modul ESG (EmissionBenefitRecorder) untuk klaim kredit emisi terhindar.
 */
final class MaritimeEsgCiiService
{
    public function __construct(
        private readonly CiiCarbonIntensityRatingEngine $ciiEngine = new CiiCarbonIntensityRatingEngine,
        private readonly ?EmissionBenefitRecorder $emissionBenefitRecorder = null
    ) {}

    /**
     * Hitung performa intensitas karbon operasional (CII) untuk satu kapal dalam satu periode pelayaran.
     *
     * @return array{
     *     vessel_id: int,
     *     imo: string,
     *     year: int,
     *     evaluation: array,
     *     carbon_avoided_grams: int,
     *     esg_recorded: bool
     * }
     */
    public function auditVesselCii(int $vesselId, int $year = 2026): array
    {
        $vessel = FleetVessel::with('voyages.noonReports')->findOrFail($vesselId);

        // Akumulasi jarak pelayaran dan konsumsi bahan bakar sepanjang tahun
        $totalDistance = 0.0;
        $totalVlsfo = 0.0;
        $totalLsmgo = 0.0;

        foreach ($vessel->voyages as $voyage) {
            $totalDistance += (float) $voyage->noonReports()->sum('distance_last_24h_nm');
            $totalVlsfo += (float) $voyage->noonReports()->sum('vlsfo_consumed_24h_mt');
            $totalLsmgo += (float) $voyage->noonReports()->sum('lsmgo_consumed_24h_mt');
        }

        // Default jika data pelayaran simulasi masih minim
        $totalDistance = max(500.0, $totalDistance);
        $totalVlsfo = max(20.0, $totalVlsfo);

        $evaluation = $this->ciiEngine->evaluateCii(
            $vessel->vessel_type,
            (float) $vessel->deadweight_tonnage,
            $totalDistance,
            [
                'vlsfo_mt' => $totalVlsfo,
                'lsmgo_mt' => $totalLsmgo,
            ],
            $year
        );

        $carbonAvoidedGrams = 0;
        $esgRecorded = false;

        // Jika kapal mencapai rating A atau B (superior), catat emisi yang berhasil dihemat ke modul ESG
        if (in_array($evaluation['rating'], ['A', 'B'], true) && $this->emissionBenefitRecorder !== null) {
            $diffGrams = (int) max(0, ($evaluation['required_cii'] - $evaluation['attained_cii']) * ($vessel->deadweight_tonnage * $totalDistance));
            $carbonAvoidedGrams = $diffGrams;

            $this->emissionBenefitRecorder->recordAvoidedEmission(
                'FLEET_VESSEL_CII',
                (int) $vessel->id,
                'CII_OP_EFFICIENCY',
                $carbonAvoidedGrams,
                CarbonImmutable::now(),
                "CII-{$vessel->imo_number}-{$year}"
            );
            $esgRecorded = true;
        }

        return [
            'vessel_id' => $vessel->id,
            'imo' => $vessel->imo_number,
            'year' => $year,
            'evaluation' => $evaluation,
            'carbon_avoided_grams' => $carbonAvoidedGrams,
            'esg_recorded' => $esgRecorded,
        ];
    }
}
