<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\SimulateHardyCrossVentilationNetworkAction;

class SimulateVentilationNetworkCommand extends Command
{
    /**
     * Signature artisan command: mining:run-ventilation.
     *
     * @var string
     */
    protected $signature = 'mining:run-ventilation
                            {--code= : Kode simulasi}
                            {--rpm=100 : Persentase kecepatan putaran fan (VSD %)}
                            {--headcount=30 : Jumlah pekerja tambang bawah tanah}
                            {--diesel-kw=450 : Total daya mesin diesel aktif (kW)}';

    protected $description = 'Menjalankan solver iteratif Hardy-Cross untuk memvalidasi sirkulasi dan kecukupan ventilasi tambang bawah tanah.';

    public function handle(SimulateHardyCrossVentilationNetworkAction $action): int
    {
        $code = $this->option('code') ?: 'VENT-CLI-'.date('Ymd-His');
        $rpm = (float) $this->option('rpm');
        $headcount = (int) $this->option('headcount');
        $dieselKw = (float) $this->option('diesel-kw');

        $this->info("Menjalankan solver Hardy-Cross untuk simulasi: {$code} (Fan VSD: {$rpm}%)...");

        $simulation = $action->execute([
            'simulation_code' => $code,
            'fan_rpm_percent' => $rpm,
            'headcount_underground' => $headcount,
            'diesel_fleet_kw' => $dieselKw,
            'branches' => [
                [
                    'id' => 'BR-SHAFT-IN-1',
                    'name' => 'Intake Main Downcast',
                    'resistance_r' => 0.040,
                    'initial_flow_q' => 140.0,
                    'fan_pressure_pf' => 1500.0 * (($rpm / 100.0) ** 2),
                ],
                [
                    'id' => 'BR-HAULAGE-DRIFT',
                    'name' => 'Haulage Level -350m',
                    'resistance_r' => 0.080,
                    'initial_flow_q' => 70.0,
                ],
                [
                    'id' => 'BR-SHAFT-RET-1',
                    'name' => 'Exhaust Upcast Shaft',
                    'resistance_r' => 0.050,
                    'initial_flow_q' => 140.0,
                ],
            ],
            'loops' => [
                [
                    'loop_id' => 'LP-PRIMARY-CIRCUIT',
                    'branch_ids' => ['BR-SHAFT-IN-1', 'BR-HAULAGE-DRIFT', 'BR-SHAFT-RET-1'],
                    'directions' => [1, 1, -1],
                ],
            ],
            'executed_by' => 'Artisan CLI Scheduler',
        ]);

        $this->info('Simulasi berhasil diselesaikan!');
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['Kode Simulasi', $simulation->simulation_code],
                ['Intake Airflow', $simulation->intake_airflow_m3_s.' m3/s'],
                ['Return Airflow', $simulation->return_airflow_m3_s.' m3/s'],
                ['Iterasi Konvergensi', $simulation->iterations_to_converge],
                ['Keseimbangan Aliran Terpenuhi', $simulation->is_converged ? 'YA' : 'TIDAK'],
                ['Status Regulasi ESDM', $simulation->status],
            ]
        );

        return self::SUCCESS;
    }
}
