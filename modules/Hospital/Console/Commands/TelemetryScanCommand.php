<?php

declare(strict_types=1);

namespace Modules\Hospital\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hospital\Domain\Algorithms\CodeBlueEscalationPlanner;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\CodeBlueEvent;
use Modules\Hospital\Domain\Models\VitalsTelemetry;
use Modules\Hospital\Domain\StateMachines\CodeBlueEventStateMachine;

/**
 * Command Terjadwal: hospital:telemetry-scan.
 * Memindai data telemetri vital bangsal dan memicu eskalasi Code Blue idempoten (Fase 87.5).
 */
class TelemetryScanCommand extends Command
{
    protected $signature = 'hospital:telemetry-scan';

    protected $description = 'Memindai telemetri vital pasien bangsal dan memicu eskalasi Code Blue jika terjadi pelanggaran ganda';

    public function handle(CodeBlueEscalationPlanner $planner): int
    {
        $this->info('Memulai pemindaian telemetri IoT (hospital:telemetry-scan)...');

        $encounters = VitalsTelemetry::select('encounter_id')->distinct()->pluck('encounter_id');
        $raisedEvents = 0;

        foreach ($encounters as $encId) {
            $readings = VitalsTelemetry::where('encounter_id', $encId)
                ->orderByDesc('id')
                ->limit(6)
                ->get()
                ->reverse()
                ->values();

            if ($readings->count() < 2) {
                continue;
            }

            $series = [];
            foreach ($readings as $idx => $r) {
                $series[] = [
                    'minute' => $idx,
                    'spo2' => (int) round((float) $r->spo2_percent),
                    'heart_rate' => (int) $r->heart_rate_bpm,
                ];
            }

            $plan = $planner->plan($series, null);
            if ($plan->isTriggered) {
                $activeEvent = CodeBlueEvent::where('encounter_id', $encId)
                    ->whereIn('status', [
                        CodeBlueEventStateMachine::STATUS_DETECTED,
                        CodeBlueEventStateMachine::STATUS_RAISED,
                        CodeBlueEventStateMachine::STATUS_ACKNOWLEDGED,
                        CodeBlueEventStateMachine::STATUS_TEAM_DISPATCHED,
                        CodeBlueEventStateMachine::STATUS_ESCALATED,
                    ])
                    ->first();

                if (! $activeEvent) {
                    $admission = Admission::where('encounter_id', $encId)->first();
                    $eventNo = 'CB-'.date('Ymd').'-'.substr(hash('sha256', "cb:{$encId}:".microtime()), 0, 6);

                    CodeBlueEvent::create([
                        'event_no' => strtoupper($eventNo),
                        'admission_id' => $admission?->id,
                        'encounter_id' => $encId,
                        'bed_id' => $admission?->bed_id,
                        'status' => CodeBlueEventStateMachine::STATUS_RAISED,
                        'escalation_level' => 'NURSE',
                        'proof_hash' => $plan->certificate->sha256,
                    ]);

                    $raisedEvents++;
                }
            }
        }

        $this->line("Pemindaian telemetri selesai. {$encounters->count()} pasien dipantau, {$raisedEvents} alarm Code Blue baru dinaikkan.");

        return self::SUCCESS;
    }
}
