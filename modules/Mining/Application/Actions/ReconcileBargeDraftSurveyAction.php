<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Models\MiningOfftakeShipment;
use Modules\Mining\Domain\Services\MineBargeDraftSurveyReconciler;

class ReconcileBargeDraftSurveyAction
{
    public function __construct(
        private readonly MineBargeDraftSurveyReconciler $reconciler = new MineBargeDraftSurveyReconciler
    ) {}

    /**
     * Rekonsiliasi tonase timbangan conveyor/weighbridge vs draft survey tongkang pelabuhan (D94-2, D94-7).
     *
     * @param array{
     *     shipment_id: string,
     *     barge_code: string,
     *     weighbridge_total_tons: int,
     *     initial_fwd_draft_m: float,
     *     initial_mid_draft_m: float,
     *     initial_aft_draft_m: float,
     *     final_fwd_draft_m: float,
     *     final_mid_draft_m: float,
     *     final_aft_draft_m: float,
     *     measured_water_density_g_cm3: float,
     *     tons_per_centimeter_immersion_tpc: float,
     *     ballast_water_diff_tons?: float
     * } $data
     * @return array{
     *     shipment_id: string,
     *     barge_code: string,
     *     weighbridge_tons: int,
     *     draft_survey_tons: int,
     *     variance_tons: int,
     *     variance_bps: int,
     *     is_within_survey_tolerance: bool,
     *     official_bill_of_lading_tonnage: int,
     *     reconciliation_status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $shipmentId = (string) $data['shipment_id'];
            $weighbridgeTons = (int) $data['weighbridge_total_tons'];

            $shipment = MiningOfftakeShipment::findOrFail($shipmentId);

            $draftResult = $this->reconciler->reconcile($data);
            $surveyTons = $draftResult['density_corrected_cargo_tons'];

            $varianceTons = $surveyTons - $weighbridgeTons;
            $varianceBps = (int) round(abs($varianceTons) * 10000 / max(1, $weighbridgeTons));

            // Toleransi diskrepansi draft survey kelautan standar ASTM / ISO: +/- 0.50% (50 bps)
            $isWithinTolerance = ($varianceBps <= 50);

            // Bill of Lading menggunakan tonase draft survey bersertifikat surveyor independen
            $officialTonnage = $surveyTons;

            $shipment->update([
                'loaded_volume_tons' => $officialTonnage,
            ]);

            return [
                'shipment_id' => $shipmentId,
                'barge_code' => $data['barge_code'],
                'weighbridge_tons' => $weighbridgeTons,
                'draft_survey_tons' => $surveyTons,
                'variance_tons' => $varianceTons,
                'variance_bps' => $varianceBps,
                'is_within_survey_tolerance' => $isWithinTolerance,
                'official_bill_of_lading_tonnage' => $officialTonnage,
                'reconciliation_status' => $isWithinTolerance ? 'SURVEY_CONCORDANT' : 'SURVEY_DISCREPANCY_DISPUTE',
            ];
        });
    }
}
