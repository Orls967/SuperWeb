<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Actions;

use Modules\Egy\Domain\Models\Microgrid;
use Modules\Shared\Domain\Algorithms\Utility\BlackStartSynchronizationSequencer;

/**
 * Aksi Simulasi Latihan Lapangan Pemulihan Black-Start Kawasan (ISO 22301 Kesiapan Kontinuitas Bisnis).
 */
final class SimulateBlackStartDrillAction
{
    public function __construct(
        private readonly BlackStartSynchronizationSequencer $sequencer,
    ) {}

    /**
     * @param  list<array{code: string, name: string, dependencies: list<string>, step_duration_seconds: int, inrush_current_amp: int}>  $components
     * @return array<string, mixed>
     */
    public function execute(string $microgridCode, array $components, string $drillCode): array
    {
        $microgrid = Microgrid::where('microgrid_code', $microgridCode)->firstOrFail();

        $result = $this->sequencer->planSequence($microgrid->microgrid_code, $components);

        $totalInrushCurrentAmp = 0;
        $maxSingleInrushAmp = 0;
        $drillSteps = [];

        foreach ($result->sequence as $step) {
            $inrush = (int) $step['inrush_current_amp'];
            $totalInrushCurrentAmp += $inrush;
            $maxSingleInrushAmp = max($maxSingleInrushAmp, $inrush);

            $drillSteps[] = [
                'step' => $step['step'],
                'component_code' => $step['component_code'],
                'component_name' => $step['component_name'],
                'duration_seconds' => $step['estimated_seconds'],
                'inrush_amp' => $inrush,
                'status' => 'drill_verified_ok',
            ];
        }

        // Kriteria kelulusan latihan: durasi total < 600 detik (10 menit) dan inrush maksimum < 500 A
        $isPassed = ($result->totalEstimatedSeconds <= 600) && ($maxSingleInrushAmp <= 500);

        $payload = sprintf(
            'BLACKSTART-DRILL|CODE:%s|GRID:%s|STEPS:%d|TIME_SEC:%d|MAX_INRUSH:%d|PASSED:%d',
            $drillCode,
            $microgridCode,
            count($components),
            $result->totalEstimatedSeconds,
            $maxSingleInrushAmp,
            $isPassed ? 1 : 0
        );
        $drillCertificateHash = hash('sha256', $payload);

        return [
            'drill_code' => $drillCode,
            'microgrid_code' => $microgridCode,
            'total_steps_count' => count($components),
            'total_recovery_duration_seconds' => $result->totalEstimatedSeconds,
            'total_inrush_current_amp' => $totalInrushCurrentAmp,
            'max_single_inrush_amp' => $maxSingleInrushAmp,
            'is_drill_passed' => $isPassed,
            'step_sequence' => $drillSteps,
            'drill_certificate_hash' => $drillCertificateHash,
        ];
    }
}
