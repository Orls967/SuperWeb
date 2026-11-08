<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Exports\CargoManifestCsvExporter;
use Modules\Aviation\Exports\FlightManifestCsvExporter;

class GenerateFlightManifestCommand extends Command
{
    protected $signature = 'avi:manifest {flight_number : Nomor penerbangan (misal: GA-404)} {--type=both : Tipe manifes (pax, cargo, both)} {--output-dir= : Direktori penyimpanan file CSV}';

    protected $description = 'Hasilkan manifes resmi penumpang dan kargo udara dalam format CSV berstandar otoritas bandara';

    public function handle(FlightManifestCsvExporter $paxExporter, CargoManifestCsvExporter $cargoExporter): int
    {
        $flightNumber = (string) $this->argument('flight_number');
        $type = (string) ($this->option('type') ?? 'both');
        $outputDir = $this->option('output-dir') ?? storage_path('app/aviation/manifests');

        $flight = AviationFlight::where('flight_number', $flightNumber)->first();
        if (! $flight) {
            $this->error("Penerbangan dengan nomor {$flightNumber} tidak ditemukan.");

            return self::FAILURE;
        }

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if (in_array($type, ['pax', 'both'], true)) {
            $paxCsv = $paxExporter->export($flight->id);
            $paxFile = "{$outputDir}/MANIFEST_PAX_{$flightNumber}_".date('Ymd_His').'.csv';
            file_put_contents($paxFile, $paxCsv);
            $this->info("Manifes Penumpang berhasil dibuat: {$paxFile}");
        }

        if (in_array($type, ['cargo', 'both'], true)) {
            $cargoCsv = $cargoExporter->export($flight->id);
            $cargoFile = "{$outputDir}/MANIFEST_CARGO_{$flightNumber}_".date('Ymd_His').'.csv';
            file_put_contents($cargoFile, $cargoCsv);
            $this->info("Manifes Kargo berhasil dibuat: {$cargoFile}");
        }

        return self::SUCCESS;
    }
}

