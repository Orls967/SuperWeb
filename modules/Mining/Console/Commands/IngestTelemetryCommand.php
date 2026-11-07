<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\IngestEquipmentTelemetryAction;
use Modules\Mining\Domain\Models\MiningEquipment;

class IngestTelemetryCommand extends Command
{
    protected $signature = 'mining:ingest-telemetry';

    protected $description = 'Melakukan simulasi ingest data telemetri IoT armada alat berat secara idempoten';

    public function handle(IngestEquipmentTelemetryAction $action): int
    {
        $this->info('Memulai ingest data telemetri IoT alat berat...');

        $equipments = MiningEquipment::all();
        if ($equipments->isEmpty()) {
            $this->warn('Tidak ada unit alat berat terdaftar.');

            return 0;
        }

        $ingested = 0;
        foreach ($equipments as $eq) {
            $seq = (int) (\Modules\Mining\Domain\Models\MiningEquipmentTelemetry::where('equipment_id', $eq->id)->max('sequence_number') ?? 0) + 1;

            $action->execute([
                'equipment_id' => (string) $eq->id,
                'sequence_number' => $seq,
                'engine_hours_total' => $eq->engine_hours + 8,
                'vibration_rms_scaled' => 1250,
                'fuel_level_percent' => 75,
                'instant_fuel_rate_ml_s' => 110,
                'payload_kg' => $eq->capacity_kg ?? 95000,
                'latitude_scaled' => 1150000,
                'longitude_scaled' => -120000,
            ]);
            $ingested++;
        }

        $this->info("Ingest telemetri berhasil: {$ingested} titik telemetri tersimpan.");

        return 0;
    }
}
