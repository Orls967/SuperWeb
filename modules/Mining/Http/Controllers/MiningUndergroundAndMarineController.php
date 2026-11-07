<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Mining\Application\Actions\AuditBargeChainOfCustodyAction;
use Modules\Mining\Application\Actions\CalculateBlastSafetyRadiusAction;
use Modules\Mining\Application\Actions\CalculateTinMeshStockpileVolumeAction;
use Modules\Mining\Application\Actions\ConductGeotechInvestigationAction;
use Modules\Mining\Application\Actions\CreateUndergroundStopeAction;
use Modules\Mining\Application\Actions\EvaluateBishopFactorOfSafetyAction;
use Modules\Mining\Application\Actions\EvaluateMuckpileKcoFragmentationAction;
use Modules\Mining\Application\Actions\ExecuteControlledDetonationAction;
use Modules\Mining\Application\Actions\ExportUndergroundMarineCsvAction;
use Modules\Mining\Application\Actions\IngestSlopeStabilityTelemetryAction;
use Modules\Mining\Application\Actions\InspectUndergroundGasLevelAction;
use Modules\Mining\Application\Actions\OptimizeMainVentilationFanPowerAction;
use Modules\Mining\Application\Actions\RecommendDrillPatternCorrectionAction;
use Modules\Mining\Application\Actions\ReconcileDroneVolumetricWithWeighbridgeAction;
use Modules\Mining\Application\Actions\ReconcileHydrostaticBargeTonnageAction;
use Modules\Mining\Application\Actions\RecordSixPointDraftSurveyAction;
use Modules\Mining\Application\Actions\RemediateSlopeStabilityAction;
use Modules\Mining\Application\Actions\ResolveGeotechIncidentAction;
use Modules\Mining\Application\Actions\SettleBargeFreightTariffAction;
use Modules\Mining\Application\Actions\SimulateHardyCrossVentilationNetworkAction;
use Modules\Mining\Application\Actions\TriggerBlastWarningSirenAction;
use Modules\Mining\Application\Actions\TriggerGeotechEvacuationAlarmAction;
use Modules\Mining\Application\Actions\VerifyBlastPerimeterClearanceAction;
use Modules\Mining\Domain\Models\MiningBargeDraftSurvey;
use Modules\Mining\Domain\Models\MiningBlastClearanceRun;
use Modules\Mining\Domain\Models\MiningBlastFragmentationEval;
use Modules\Mining\Domain\Models\MiningGeotechIncident;
use Modules\Mining\Domain\Models\MiningQuarryDroneSurvey;
use Modules\Mining\Domain\Models\MiningUndergroundStope;
use Modules\Mining\Domain\Models\MiningVentilationSimulation;
use Modules\Mining\Http\Requests\BlastClearanceRequest;
use Modules\Mining\Http\Requests\CreateUndergroundStopeRequest;
use Modules\Mining\Http\Requests\RecordDraftSurveyRequest;
use Modules\Mining\Http\Requests\SimulateVentilationNetworkRequest;
use Modules\Mining\Http\Requests\SlopeTelemetryRequest;

/**
 * Controller: Antarmuka HTTP & REST API untuk Fase 119
 * (Underground Stoping, Hardy-Cross Ventilation, Drill & Blast KCO, Geotech Slope Radar, Marine Barge Draft Survey).
 */
class MiningUndergroundAndMarineController extends Controller
{
    public function index(): Response
    {
        $activeStopes = MiningUndergroundStope::query()->latest()->take(10)->get();
        $ventSims = MiningVentilationSimulation::query()->latest()->take(5)->get();
        $blastRuns = MiningBlastClearanceRun::query()->latest()->take(10)->get();
        $geotechIncidents = MiningGeotechIncident::query()->latest()->take(10)->get();
        $droneSurveys = MiningQuarryDroneSurvey::query()->latest()->take(10)->get();
        $bargeSurveys = MiningBargeDraftSurvey::query()->latest()->take(10)->get();

        return response()->view('mining::underground', [
            'activeStopes' => $activeStopes,
            'ventSims' => $ventSims,
            'blastRuns' => $blastRuns,
            'geotechIncidents' => $geotechIncidents,
            'droneSurveys' => $droneSurveys,
            'bargeSurveys' => $bargeSurveys,
        ]);
    }

    public function blastView(): Response
    {
        $blastRuns = MiningBlastClearanceRun::query()->latest()->paginate(15);
        $evaluations = MiningBlastFragmentationEval::query()->latest()->take(10)->get();

        return response()->view('mining::blast', [
            'blastRuns' => $blastRuns,
            'evaluations' => $evaluations,
        ]);
    }

    public function geotechView(): Response
    {
        $incidents = MiningGeotechIncident::query()->latest()->paginate(15);
        $droneSurveys = MiningQuarryDroneSurvey::query()->latest()->take(10)->get();

        return response()->view('mining::geotech', [
            'incidents' => $incidents,
            'droneSurveys' => $droneSurveys,
        ]);
    }

    public function bargeView(): Response
    {
        $surveys = MiningBargeDraftSurvey::query()->latest()->paginate(15);

        return response()->view('mining::barge', [
            'surveys' => $surveys,
        ]);
    }

    public function createStope(CreateUndergroundStopeRequest $request, CreateUndergroundStopeAction $action): JsonResponse
    {
        $stope = $action->execute($request->validated());

        return response()->json([
            'message' => 'Stope bawah tanah berhasil didaftarkan.',
            'stope' => $stope,
        ], 201);
    }

    public function simulateVentilation(SimulateVentilationNetworkRequest $request, SimulateHardyCrossVentilationNetworkAction $action): JsonResponse
    {
        $sim = $action->execute($request->validated());

        return response()->json([
            'message' => 'Simulasi jaringan ventilasi Hardy-Cross selesai dieksekusi.',
            'simulation' => $sim,
        ]);
    }

    public function createBlastRun(BlastClearanceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $code = 'BLAST-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid('', true)), 0, 4));

        $run = new MiningBlastClearanceRun;
        $run->fill([
            'run_code' => $code,
            'pattern_id' => $data['pattern_id'],
            'pit_name' => $data['pit_name'],
            'center_latitude' => (float) $data['center_latitude'],
            'center_longitude' => (float) $data['center_longitude'],
            'safety_radius_meters' => (float) $data['safety_radius_meters'],
            'planned_holes_count' => (int) $data['planned_holes_count'],
            'total_explosives_kg' => (float) $data['total_explosives_kg'],
            'status' => 'DRAFT',
            'all_personnel_evacuated' => false,
        ]);
        $run->save();

        return response()->json([
            'message' => 'Rencana peledakan berhasil dibuat.',
            'blast_run' => $run,
        ], 201);
    }

    public function verifyClearance(Request $request, VerifyBlastPerimeterClearanceAction $action): JsonResponse
    {
        $validated = $request->validate([
            'blast_clearance_run_id' => ['required', 'string'],
        ]);

        $result = $action->execute($validated);

        return response()->json($result);
    }

    public function triggerSiren(Request $request, TriggerBlastWarningSirenAction $action): JsonResponse
    {
        $validated = $request->validate([
            'blast_clearance_run_id' => ['required', 'string'],
            'siren_stage' => ['required', 'string'],
            'blaster_in_charge_kjl' => ['required', 'string'],
        ]);

        $result = $action->execute($validated);

        return response()->json($result);
    }

    public function detonate(Request $request, ExecuteControlledDetonationAction $action): JsonResponse
    {
        $validated = $request->validate([
            'blast_clearance_run_id' => ['required', 'string'],
            'initiated_by_user_id' => ['required', 'string'],
            'detonator_box_serial' => ['required', 'string'],
            'explosive_anfo_kg' => ['nullable', 'numeric'],
            'explosive_emulsion_kg' => ['nullable', 'numeric'],
            'total_explosive_cost_idr' => ['nullable', 'numeric'],
        ]);

        $result = $action->execute($validated);

        return response()->json($result);
    }

    public function evaluateFragmentation(Request $request, EvaluateMuckpileKcoFragmentationAction $action): JsonResponse
    {
        $validated = $request->validate([
            'blast_clearance_run_id' => ['required', 'string'],
            'eval_code' => ['required', 'string'],
            'rock_factor_a' => ['required', 'numeric', 'gt:0'],
            'burden_meters' => ['required', 'numeric', 'gt:0'],
            'spacing_meters' => ['required', 'numeric', 'gt:0'],
            'hole_diameter_mm' => ['required', 'numeric', 'gt:0'],
            'bench_height_meters' => ['required', 'numeric', 'gt:0'],
            'charge_length_meters' => ['required', 'numeric', 'gt:0'],
            'explosive_rws' => ['required', 'numeric', 'gt:0'],
            'explosive_density' => ['required', 'numeric', 'gt:0'],
        ]);

        $eval = $action->execute($validated);

        return response()->json([
            'message' => 'Evaluasi fragmentasi model KCO berhasil.',
            'evaluation' => $eval,
        ]);
    }

    public function ingestSlopeTelemetry(SlopeTelemetryRequest $request, IngestSlopeStabilityTelemetryAction $action): JsonResponse
    {
        $incident = $action->execute($request->validated());

        return response()->json([
            'message' => 'Telemetri lereng berhasil diunggah.',
            'incident' => $incident,
        ], 201);
    }

    public function triggerEvacuation(Request $request, TriggerGeotechEvacuationAlarmAction $action): JsonResponse
    {
        $validated = $request->validate([
            'geotech_incident_id' => ['required', 'string'],
            'ordered_by_ktt_name' => ['required', 'string'],
            'evacuation_reason' => ['required', 'string'],
            'safe_muster_point_sector' => ['required', 'string'],
        ]);

        $result = $action->execute($validated);

        return response()->json($result);
    }

    public function resolveIncident(Request $request, ResolveGeotechIncidentAction $action): JsonResponse
    {
        $validated = $request->validate([
            'geotech_incident_id' => ['required', 'string'],
            'closing_engineer_name' => ['required', 'string'],
            'verified_stable_displacement_rate_mm_h' => ['required', 'numeric'],
            'verified_factor_of_safety' => ['required', 'numeric'],
            'signoff_notes' => ['required', 'string'],
        ]);

        $result = $action->execute($validated);

        return response()->json($result);
    }

    public function recordDraft(RecordDraftSurveyRequest $request, RecordSixPointDraftSurveyAction $action): JsonResponse
    {
        $survey = $action->execute($request->validated());

        return response()->json([
            'message' => 'Data draft survey 6-titik berhasil dicatat.',
            'survey' => $survey,
        ], 201);
    }

    public function reconcileBarge(Request $request, ReconcileHydrostaticBargeTonnageAction $action): JsonResponse
    {
        $validated = $request->validate([
            'barge_code' => ['required', 'string'],
            'initial_survey_id' => ['required', 'string'],
            'final_survey_id' => ['required', 'string'],
            'weighbridge_loaded_tonnage_mt' => ['nullable', 'numeric'],
        ]);

        $result = $action->execute($validated);

        return response()->json($result);
    }

    public function auditChainOfCustody(Request $request, AuditBargeChainOfCustodyAction $action): JsonResponse
    {
        $validated = $request->validate([
            'barge_draft_survey_id' => ['required', 'string'],
            'jetty_id' => ['required', 'string'],
            'syahbandar_spb_number' => ['required', 'string'],
            'certificate_of_analysis_number' => ['required', 'string'],
            'tamper_seal_numbers' => ['required', 'array', 'min:1'],
            'auditor_name' => ['required', 'string'],
            'destination_port' => ['required', 'string'],
        ]);

        $result = $action->execute($validated);

        return response()->json($result);
    }

    public function settleFreight(Request $request, SettleBargeFreightTariffAction $action): JsonResponse
    {
        $validated = $request->validate([
            'barge_draft_survey_id' => ['required', 'string'],
            'contract_freight_rate_per_ton_idr' => ['required', 'numeric', 'gt:0'],
            'allowed_laytime_hours' => ['required', 'numeric', 'gt:0'],
            'actual_laytime_hours' => ['required', 'numeric', 'gt:0'],
            'demurrage_rate_per_day_idr' => ['required', 'numeric', 'gt:0'],
        ]);

        $result = $action->execute($validated);

        return response()->json($result);
    }

    public function exportCsv(Request $request, ExportUndergroundMarineCsvAction $action): Response
    {
        $domain = $request->query('domain', 'ALL');
        $result = $action->execute($domain);

        return response($result['csv_content'], 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }

    public function calculateBlastRadius(Request $request, CalculateBlastSafetyRadiusAction $action): JsonResponse
    {
        $validated = $request->validate([
            'explosives_weight_kg' => ['required', 'numeric', 'gt:0'],
            'distance_meters' => ['nullable', 'numeric'],
            'rock_density' => ['nullable', 'numeric'],
        ]);

        $res = $action->execute(
            explosivesWeightKg: (float) $validated['explosives_weight_kg'],
            distanceMeters: (float) ($validated['distance_meters'] ?? 100.0),
            rockDensityGPerCm3: (float) ($validated['rock_density'] ?? 2.65)
        );

        return response()->json($res);
    }

    public function calculateTinMeshVolume(Request $request, CalculateTinMeshStockpileVolumeAction $action): JsonResponse
    {
        $validated = $request->validate([
            'points' => ['required', 'array', 'min:3'],
            'bulk_density_t_m3' => ['nullable', 'numeric'],
        ]);

        $res = $action->execute(
            pointCloudCoordinates: $validated['points'],
            bulkDensityTM3: (float) ($validated['bulk_density_t_m3'] ?? 1.65)
        );

        return response()->json($res);
    }

    public function investigateGeotech(Request $request, ConductGeotechInvestigationAction $action): JsonResponse
    {
        $validated = $request->validate([
            'incident_id' => ['required', 'string'],
            'inspector_name' => ['required', 'string'],
            'observations' => ['required', 'string'],
            'risk_level' => ['required', 'string'],
        ]);

        $res = $action->execute(
            incidentId: $validated['incident_id'],
            inspectorName: $validated['inspector_name'],
            observations: $validated['observations'],
            riskLevel: $validated['risk_level']
        );

        return response()->json($res);
    }

    public function evaluateBishopFos(Request $request, EvaluateBishopFactorOfSafetyAction $action): JsonResponse
    {
        $validated = $request->validate([
            'slices' => ['required', 'array', 'min:1'],
        ]);

        $res = $action->execute($validated['slices']);

        return response()->json($res);
    }

    public function inspectGasLevel(Request $request, InspectUndergroundGasLevelAction $action): JsonResponse
    {
        $validated = $request->validate([
            'readings' => ['required', 'array'],
        ]);

        $res = $action->execute($validated['readings']);

        return response()->json($res);
    }

    public function optimizeFanPower(Request $request, OptimizeMainVentilationFanPowerAction $action): JsonResponse
    {
        $validated = $request->validate([
            'required_flow_m3_s' => ['required', 'numeric', 'gt:0'],
            'system_resistance_r' => ['required', 'numeric', 'gt:0'],
        ]);

        $res = $action->execute(
            requiredFlowM3S: (float) $validated['required_flow_m3_s'],
            systemResistanceR: (float) $validated['system_resistance_r']
        );

        return response()->json($res);
    }

    public function recommendDrillCorrection(Request $request, RecommendDrillPatternCorrectionAction $action): JsonResponse
    {
        $validated = $request->validate([
            'evaluation_id' => ['required', 'string'],
        ]);

        $res = $action->execute($validated['evaluation_id']);

        return response()->json($res);
    }

    public function reconcileDroneWithWeighbridge(Request $request, ReconcileDroneVolumetricWithWeighbridgeAction $action): JsonResponse
    {
        $validated = $request->validate([
            'survey_id' => ['required', 'string'],
            'weighbridge_tonnage' => ['required', 'numeric', 'gt:0'],
        ]);

        $res = $action->execute(
            surveyId: $validated['survey_id'],
            weighbridgeTonnage: (float) $validated['weighbridge_tonnage']
        );

        return response()->json($res);
    }

    public function remediateSlope(Request $request, RemediateSlopeStabilityAction $action): JsonResponse
    {
        $validated = $request->validate([
            'geotech_incident_id' => ['required', 'string'],
            'remediation_type' => ['required', 'string'],
            'contractor_name' => ['required', 'string'],
            'estimated_cost_idr' => ['required', 'numeric', 'gt:0'],
            'target_factor_of_safety' => ['required', 'numeric'],
        ]);

        $res = $action->execute($validated);

        return response()->json($res);
    }
}
