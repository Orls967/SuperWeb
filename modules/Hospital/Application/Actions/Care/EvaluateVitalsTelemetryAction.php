<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Algorithms\CodeBlueEscalationPlanner;
use Modules\Hospital\Domain\Algorithms\EarlyWarningScoreCalculator;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\CodeBlueEvent;
use Modules\Hospital\Domain\Models\VitalsTelemetry;
use Modules\Hospital\Domain\StateMachines\CodeBlueEventStateMachine;

/**
 * Aksi Evaluasi Telemetri Tanda Vital & Deteksi Code Blue (Fase 87.5).
 * Menghitung skor NEWS2 via B2 dan mendeteksi pemicu Code Blue via B5.
 */
class EvaluateVitalsTelemetryAction
{
    public function __construct(
        protected readonly EarlyWarningScoreCalculator $news2Calculator,
        protected readonly CodeBlueEscalationPlanner $codeBluePlanner
    ) {}

    /**
     * @param array{
     *     encounter_id: int,
     *     respiration_rate: int,
     *     spo2_percent: int,
     *     supplemental_oxygen: bool,
     *     systolic_bp: int,
     *     heart_rate: int,
     *     consciousness: string,
     *     temp_deci_c: int,
     *     recorded_minute?: int
     * } $data
     * @return array{
     *     vitals_id: int,
     *     news2_score: int,
     *     risk_category: string,
     *     code_blue_triggered: bool,
     *     code_blue_event_id: ?int
     * }
     */
    public function execute(array $data): array
    {
        $encounterId = $data['encounter_id'];
        $spo2 = $data['spo2_percent'];
        $hr = $data['heart_rate'];
        $minute = $data['recorded_minute'] ?? (int) (time() / 60);

        // 1. Evaluasi skor NEWS2 (B2)
        $news2Result = $this->news2Calculator->calculate(
            respirationRate: $data['respiration_rate'],
            spo2Percent: $spo2,
            supplementalOxygen: $data['supplemental_oxygen'],
            systolicBp: $data['systolic_bp'],
            heartRate: $hr,
            consciousness: $data['consciousness'],
            tempDeciC: $data['temp_deci_c']
        );

        // 2. Simpan pembacaan telemetri ke basis data
        $proofHash = hash('sha256', "vitals:{$encounterId}:{$spo2}:{$hr}:{$news2Result->totalScore}:".microtime());
        $vitals = VitalsTelemetry::create([
            'encounter_id' => $encounterId,
            'spo2_percent' => $spo2,
            'heart_rate_bpm' => $hr,
            'temp_c' => $data['temp_deci_c'] / 10.0,
            'code_blue_triggered' => false,
            'proof_hash' => $proofHash,
            'recorded_at' => now(),
        ]);

        // 3. Ambil riwayat telemetri terkini untuk deteksi Code Blue (B5)
        $recentVitals = VitalsTelemetry::where('encounter_id', $encounterId)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->reverse()
            ->values();

        $telemetrySeries = [];
        $baseMin = $minute - count($recentVitals) + 1;
        foreach ($recentVitals as $idx => $v) {
            $telemetrySeries[] = [
                'minute' => $baseMin + $idx,
                'spo2' => (int) round((float) $v->spo2_percent),
                'heart_rate' => (int) $v->heart_rate_bpm,
            ];
        }

        $codeBlueResult = $this->codeBluePlanner->plan($telemetrySeries, null);

        $codeBlueEvent = null;
        if ($codeBlueResult->isTriggered) {
            // Cek apakah sudah ada event code blue aktif pada encounter ini (idempoten)
            $existingActive = CodeBlueEvent::where('encounter_id', $encounterId)
                ->whereIn('status', [
                    CodeBlueEventStateMachine::STATUS_DETECTED,
                    CodeBlueEventStateMachine::STATUS_RAISED,
                    CodeBlueEventStateMachine::STATUS_ACKNOWLEDGED,
                    CodeBlueEventStateMachine::STATUS_TEAM_DISPATCHED,
                    CodeBlueEventStateMachine::STATUS_ESCALATED,
                ])
                ->first();

            if (! $existingActive) {
                $eventNo = 'CB-'.date('Ymd').'-'.substr(hash('sha256', "cb:{$encounterId}:{$minute}"), 0, 6);
                $admission = Admission::where('encounter_id', $encounterId)->first();

                $codeBlueEvent = CodeBlueEvent::create([
                    'event_no' => strtoupper($eventNo),
                    'admission_id' => $admission?->id,
                    'encounter_id' => $encounterId,
                    'bed_id' => $admission?->bed_id,
                    'status' => CodeBlueEventStateMachine::STATUS_RAISED,
                    'escalation_level' => 'NURSE',
                    'proof_hash' => $codeBlueResult->certificate->sha256,
                ]);

                $vitals->code_blue_triggered = true;
                $vitals->save();
            } else {
                $codeBlueEvent = $existingActive;
            }
        }

        return [
            'vitals_id' => $vitals->id,
            'news2_score' => $news2Result->totalScore,
            'risk_category' => $news2Result->riskCategory,
            'code_blue_triggered' => $codeBlueResult->isTriggered,
            'code_blue_event_id' => $codeBlueEvent?->id,
        ];
    }
}
