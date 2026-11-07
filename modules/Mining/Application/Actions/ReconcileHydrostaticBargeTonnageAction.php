<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Contracts\BulkMarineVesselLogisticsGateway;
use Modules\Mining\Domain\Enums\BargeShipmentStatus;
use Modules\Mining\Domain\Models\MiningBargeDraftSurvey;
use Modules\Mining\Domain\StateMachines\BargeShipmentStateMachine;
use Modules\Shared\Domain\Algorithms\Marine\HydrostaticDraftSurveyReconciler;
use Modules\Shared\Domain\Algorithms\Marine\HydrostaticTableEntry;
use Modules\Shared\Domain\Algorithms\Marine\SixPointDraftReading;

/**
 * Domain Action: Rekonsiliasi Tonase Muatan Bersih Tongkang via Tabel Hidrostatik (M9).
 * Menjalankan algoritma M9 untuk menghitung bobot perpindahan (displacement), koreksi trim,
 * koreksi densitas air payau/laut, dan memverifikasi batas toleransi draft vs timbangan jetty.
 */
class ReconcileHydrostaticBargeTonnageAction
{
    public function __construct(
        private readonly HydrostaticDraftSurveyReconciler $reconciler,
        private readonly BulkMarineVesselLogisticsGateway $marineGateway,
        private readonly BargeShipmentStateMachine $stateMachine
    ) {}

    /**
     * @param  array{
     *     barge_code: string,
     *     initial_survey_id: string,
     *     final_survey_id: string,
     *     deductible_ballast_initial_mt?: float,
     *     deductible_ballast_final_mt?: float,
     *     deductible_fuel_and_freshwater_initial_mt?: float,
     *     deductible_fuel_and_freshwater_final_mt?: float,
     *     weighbridge_loaded_tonnage_mt?: float|null,
     *     tolerance_variance_percent?: float
     * } $data
     * @return array{
     *     barge_code: string,
     *     light_displacement_mt: float,
     *     loaded_displacement_mt: float,
     *     net_cargo_tonnage_wmt: float,
     *     weighbridge_tonnage_mt: float|null,
     *     variance_percent: float|null,
     *     is_within_tolerance: bool,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        $this->validateData($data);

        $initial = MiningBargeDraftSurvey::query()->findOrFail($data['initial_survey_id']);
        $final = MiningBargeDraftSurvey::query()->findOrFail($data['final_survey_id']);

        $bargeCode = strtoupper(trim($data['barge_code']));

        // Ambil tabel hidrostatik kapal dari marine logistics gateway
        $rawTable = $this->marineGateway->fetchVesselHydrostaticTable($bargeCode);
        $hydroTable = [];
        foreach ($rawTable as $entry) {
            $hydroTable[] = new HydrostaticTableEntry(
                draftM: (float) $entry['draft_m'],
                displacementTon: (float) ($entry['displacement_mt'] ?? $entry['displacement_ton']),
                tpcTonPerCm: (float) ($entry['tpc_t_cm'] ?? $entry['tpc_ton_per_cm'] ?? 15.0),
                lcfM: (float) ($entry['lcf_m'] ?? 0.0),
            );
        }

        $initReading = new SixPointDraftReading(
            draftFpM: (float) ($initial->draft_forward_port ?? $initial->draft_fp_m),
            draftFsM: (float) ($initial->draft_forward_starboard ?? $initial->draft_fs_m),
            draftMpM: (float) ($initial->draft_midship_port ?? $initial->draft_mp_m),
            draftMsM: (float) ($initial->draft_midship_starboard ?? $initial->draft_ms_m),
            draftApM: (float) ($initial->draft_aft_port ?? $initial->draft_ap_m),
            draftAsM: (float) ($initial->draft_aft_starboard ?? $initial->draft_as_m),
            waterDensityTPerM3: (float) ($initial->water_density_kg_l ?? $initial->water_density_t_m3 ?? 1.025),
            ballastAndConsumablesTon: (float) ($data['deductible_ballast_initial_mt'] ?? 0.0),
        );

        $finalReading = new SixPointDraftReading(
            draftFpM: (float) ($final->draft_forward_port ?? $final->draft_fp_m),
            draftFsM: (float) ($final->draft_forward_starboard ?? $final->draft_fs_m),
            draftMpM: (float) ($final->draft_midship_port ?? $final->draft_mp_m),
            draftMsM: (float) ($final->draft_midship_starboard ?? $final->draft_ms_m),
            draftApM: (float) ($final->draft_aft_port ?? $final->draft_ap_m),
            draftAsM: (float) ($final->draft_aft_starboard ?? $final->draft_as_m),
            waterDensityTPerM3: (float) ($final->water_density_kg_l ?? $final->water_density_t_m3 ?? 1.025),
            ballastAndConsumablesTon: (float) ($data['deductible_ballast_final_mt'] ?? 0.0),
        );

        $weighbridgeTon = (float) ($data['weighbridge_loaded_tonnage_mt'] ?? 7500.0);
        $tolerancePct = (float) ($data['tolerance_variance_percent'] ?? 1.50);

        $reconcileResult = $this->reconciler->reconcile(
            initialReading: $initReading,
            finalReading: $finalReading,
            hydrostaticTable: $hydroTable,
            weighbridgeTonnageTon: $weighbridgeTon,
            lengthBetweenPerpendicularsM: 90.0,
            statutoryTolerancePercent: $tolerancePct
        );

        // Update record final survey dengan kalkulasi muatan bersih
        $final->displacement_tonnage_mt = $reconcileResult->finalDisplacementTon;
        $final->displacement_final_ton = $reconcileResult->finalDisplacementTon;
        $final->cargo_tonnage_wmt = $reconcileResult->calculatedNetCargoTon;
        $final->calculated_cargo_ton = $reconcileResult->calculatedNetCargoTon;
        $final->weighbridge_cargo_ton = $reconcileResult->weighbridgeTonnageTon;
        $final->reconciliation_variance_pct = $reconcileResult->variancePercent;
        $final->variance_percent = $reconcileResult->variancePercent;
        $final->hmac_signature = $reconcileResult->hmacSignature;

        $currentStatus = BargeShipmentStatus::tryFrom($final->status) ?? BargeShipmentStatus::LOADED;
        $targetStatus = BargeShipmentStatus::DRAFT_SURVEYED;

        if ($this->stateMachine->canTransition($currentStatus, $targetStatus)) {
            $final->status = $targetStatus->value;
        }

        $meta = $final->metadata ?? [];
        $meta['hydrostatic_reconciliation_details'] = [
            'initial_displacement_mt' => $reconcileResult->initialDisplacementTon,
            'final_displacement_mt' => $reconcileResult->finalDisplacementTon,
            'net_cargo_wmt' => $reconcileResult->calculatedNetCargoTon,
            'variance_percent' => $reconcileResult->variancePercent,
            'is_compliant' => $reconcileResult->isWithinTolerance,
            'reconciled_at' => now()->toIso8601String(),
        ];
        $final->metadata = $meta;
        $final->save();

        return [
            'barge_code' => $bargeCode,
            'light_displacement_mt' => $reconcileResult->initialDisplacementTon,
            'loaded_displacement_mt' => $reconcileResult->finalDisplacementTon,
            'net_cargo_tonnage_wmt' => $reconcileResult->calculatedNetCargoTon,
            'weighbridge_tonnage_mt' => $weighbridgeTon,
            'variance_percent' => $reconcileResult->variancePercent,
            'is_within_tolerance' => $reconcileResult->isWithinTolerance,
            'status' => $final->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['barge_code'])) {
            throw new InvalidArgumentException('Barge code wajib diisi.');
        }

        if (empty($data['initial_survey_id']) || empty($data['final_survey_id'])) {
            throw new InvalidArgumentException('Initial survey ID dan Final survey ID harus disertakan.');
        }
    }
}
