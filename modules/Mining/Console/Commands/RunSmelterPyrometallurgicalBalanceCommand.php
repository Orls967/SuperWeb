<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\ExecuteSmelterEnergyBalanceAction;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

class RunSmelterPyrometallurgicalBalanceCommand extends Command
{
    protected $signature = 'mining:run-smelter-balance
                            {run_code? : Kode batch peleburan tanur (kosong = batch draft terkini)}
                            {--power-mwh= : Override konsumsi daya listrik terukur (MWh)}';

    protected $description = 'Menjalankan solver termodinamika neraca massa dan energi tanur peleburan smelter RKEF.';

    public function handle(ExecuteSmelterEnergyBalanceAction $action): int
    {
        $runCode = $this->argument('run_code');

        $query = MiningSmelterFurnaceRun::query();
        if ($runCode) {
            $query->where('run_code', $runCode);
        } else {
            $query->latest();
        }

        $run = $query->first();

        if (! $run) {
            $this->error('Tidak ditemukan data siklus batch peleburan tanur smelter.');

            return self::FAILURE;
        }

        $this->info("Menghitung neraca massa & energi pyrometallurgical untuk batch: {$run->run_code}...");

        $powerMwh = $this->option('power-mwh') ? (float) $this->option('power-mwh') : null;
        $result = $action->execute($run, $powerMwh);

        $this->table(
            ['Metrik', 'Nilai', 'Satuan'],
            [
                ['Umpan Kering (Ore DMT)', number_format($result->dryFeedTons, 2), 'Ton'],
                ['Logam Tertuang (NPI Output)', number_format($result->metalTappedTons, 2), 'Ton'],
                ['Kadar Nikel Logam (Ni Grade)', number_format($result->metalNiGradePct, 2), '%'],
                ['Terak Tertuang (Slag Output)', number_format($result->slagTappedTons, 2), 'Ton'],
                ['Daya Listrik Terpakai', number_format($result->actualPowerConsumedMwh, 2), 'MWh'],
                ['Konsumsi Spesifik Daya', number_format($result->specificEnergyConsumptionMwhPerTonMetal, 2), 'MWh/t Metal'],
                ['Efisiensi Termal Tanur', number_format($result->thermalEfficiencyPct, 2), '%'],
                ['Total Emisi GRK', number_format($result->grossEmissionsTco2e, 2), 'tCO2e'],
                ['Intensitas Emisi Logam', number_format($result->emissionIntensityTco2ePerTonMetal, 3), 'tCO2e/t Metal'],
                ['Pajak Karbon Terlampaui', $result->carbonTaxThresholdExceeded ? 'YA (> 2.5 tCO2e/t)' : 'TIDAK (Patuh)', 'Status'],
            ]
        );

        $this->info('Perhitungan neraca metalurgi dan emisi tanur selesai sukses.');

        return self::SUCCESS;
    }
}
