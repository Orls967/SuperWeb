<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\TriggerGeotechEvacuationAlarmAction;
use Modules\Mining\Domain\Models\MiningGeotechIncident;

class ScanGeotechSlopeThresholdCommand extends Command
{
    /**
     * Signature artisan command: mining:check-slope.
     *
     * @var string
     */
    protected $signature = 'mining:check-slope
                            {--threshold=5.0 : Ambang batas laju deformasi kritis (mm/jam)}
                            {--auto-evacuate : Secara otomatis membunyikan alarm evakuasi jika kritis}';

    protected $description = 'Memindai ambang batas deformasi radar lereng geoteknik dan memicu protokol mitigasi bahaya longsor.';

    public function handle(TriggerGeotechEvacuationAlarmAction $evacuationAction): int
    {
        $threshold = (float) $this->option('threshold');
        $autoEvacuate = (bool) $this->option('auto-evacuate');

        $this->info("Memindai radar lereng dengan ambang kritis >= {$threshold} mm/jam...");

        $criticalIncidents = MiningGeotechIncident::query()
            ->where('displacement_rate_mm_hour', '>=', $threshold)
            ->where('status', '!=', 'RESOLVED')
            ->get();

        if ($criticalIncidents->isEmpty()) {
            $this->info('Seluruh lereng tambang dalam batas stabil aman (0 pergerakan kritis terdeteksi).');

            return self::SUCCESS;
        }

        $this->warn("Ditemukan {$criticalIncidents->count()} sektor lereng dengan deformasi kritis!");

        foreach ($criticalIncidents as $incident) {
            $this->line("- [{$incident->incident_code}] Sektor: {$incident->location_sector} | Laju: {$incident->displacement_rate_mm_hour} mm/h | Status: {$incident->status}");

            if ($autoEvacuate && ! $incident->is_evacuation_ordered) {
                $evacuationAction->execute([
                    'geotech_incident_id' => $incident->id,
                    'ordered_by_ktt_name' => 'Automated Geotech Radar Surveillance System',
                    'evacuation_reason' => "Laju deformasi lereng {$incident->displacement_rate_mm_hour} mm/h melebihi ambang batas {$threshold} mm/h.",
                    'safe_muster_point_sector' => 'Muster Point Alpha Main Gate',
                ]);
                $this->error("  -> ALARM EVAKUASI DIAKTIFKAN UNTUK SEKTOR {$incident->location_sector}!");
            }
        }

        return self::SUCCESS;
    }
}
