<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Domain\Services\MineCarbonEmissionCalculator;

class BenchmarkMineEmissionsCommand extends Command
{
    protected $signature = 'mining:benchmark-emissions {--diesel=100000 : Liter solar} {--kwh=250000 : Listrik kWh} {--production=50000 : Ton komoditas}';

    protected $description = 'Benchmark intensitas emisi gas rumah kaca Scope 1 dan Scope 2 terhadap standar internasional CBAM Ditjen EBTKE';

    public function handle(MineCarbonEmissionCalculator $calculator): int
    {
        $diesel = (int) $this->option('diesel');
        $kwh = (int) $this->option('kwh');
        $prod = (int) $this->option('production');

        $this->info("Menghitung benchmark emisi karbon tambang ({$prod} ton produksi)...");

        $result = $calculator->calculate([
            'diesel_consumed_liters' => $diesel,
            'electricity_consumed_kwh' => $kwh,
            'total_production_tons' => $prod,
        ]);

        $this->line("Scope 1 (BBM Solar)    : {$result['total_scope1_kg_co2e']} kg CO2e");
        $this->line("Scope 2 (Listrik Grid) : {$result['scope2_electricity_emissions_kg_co2e']} kg CO2e");
        $this->line("Total Emisi Bruto      : {$result['total_gross_emissions_ton_co2e']} Ton CO2e");
        $this->line("Intensitas Emisi       : {$result['emission_intensity_kg_co2e_per_ton']} kg CO2e/ton");
        $this->line("Status Benchmark CBAM  : {$result['cbam_benchmark_status']}");

        $this->info('Benchmark emisi karbon operasional selesai.');

        return 0;
    }
}
