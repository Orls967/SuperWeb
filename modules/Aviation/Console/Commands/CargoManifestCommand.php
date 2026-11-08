<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Aviation\Domain\Models\AviationFlight;

class CargoManifestCommand extends Command
{
    protected $signature = 'aviation:cargo-manifest {flight : ID atau Nomor Penerbangan}';

    protected $description = 'Menghasilkan manifes muatan kargo udara berstandar IATA untuk penerbangan terkait';

    public function handle(): int
    {
        $flightArg = $this->argument('flight');
        $flight = is_numeric($flightArg)
            ? AviationFlight::with('cargoConsignments')->find($flightArg)
            : AviationFlight::with('cargoConsignments')->where('flight_number', $flightArg)->first();

        if (! $flight) {
            $this->error("Penerbangan {$flightArg} tidak ditemukan.");
            return self::FAILURE;
        }

        $consignments = $flight->cargoConsignments;
        $this->info("=== MANIFEST KARGO UDARA: {$flight->flight_number} ===");
        $this->line("- Status Penerbangan: {$flight->status}");
        $this->line("- Jumlah Muatan: {$consignments->count()} koli");

        $totalWeightG = 0;
        foreach ($consignments as $c) {
            $totalWeightG += $c->weight_g;
            $dgInfo = $c->is_dg ? "[DG: {$c->un_number}]" : "[NON-DG]";
            $this->line("  * AWB {$c->consignment_number}: {$c->weight_g} g | {$c->nature_of_goods} {$dgInfo} | Status: {$c->status}");
        }

        $this->info("Total Berat Kargo: ".($totalWeightG / 1000)." kg.");

        return self::SUCCESS;
    }
}

