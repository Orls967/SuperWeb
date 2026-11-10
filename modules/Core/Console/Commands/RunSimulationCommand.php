<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\SimulationRunner;
use Modules\Core\Domain\Models\SimRun;

class RunSimulationCommand extends Command
{
    protected $signature = 'sim:run
        {--days=1 : Jumlah hari virtual yang disimulasikan}
        {--seed=42 : Seed deterministik bilangan bulat}
        {--name=default : Nama unik sesi simulasi}
        {--resume : Melanjutkan simulasi dari checkpoint terakhir}
        {--mode=live : Mode eksekusi: live atau sandbox}';

    protected $description = 'Menjalankan atau melanjutkan virtual clock Simulation Kernel secara deterministik dengan tick handlers dan checkpoint kriptografis harian';

    public function handle(SimulationRunner $runner): int
    {
        $days = max(1, (int) $this->option('days'));
        $seed = (int) $this->option('seed');
        $name = (string) $this->option('name');
        $resume = (bool) $this->option('resume');
        $mode = (string) $this->option('mode');

        $this->info("Menyiapkan eksekusi Simulation Kernel [{$name}] (mode: {$mode})...");

        if ($resume) {
            $existing = SimRun::where('name', $name)->first();
            if (! $existing) {
                $this->error("Tidak dapat melanjutkan: Sesi simulasi [{$name}] belum pernah dijalankan.");

                return self::FAILURE;
            }

            $this->info("Melanjutkan simulasi [{$name}] dari checkpoint terakhir...");
            $runner->resume($existing->id, $days);
            $fingerprint = $runner->fingerprint($existing->id);
            $this->info("Simulation Run [{$name}]: Advanced {$days} days.");
            $this->info("Simulasi berhasil dilanjutkan. Fingerprint akhir: {$fingerprint}");

            return self::SUCCESS;
        }

        $runId = $runner->start($name, $days, $seed, $mode);
        $fingerprint = $runner->fingerprint($runId);

        $this->info("Simulation Run [{$name}]: Advanced {$days} days.");
        $this->info("Simulasi [{$name}] tuntas selama {$days} hari virtual (seed: {$seed}).");
        $this->info("Fingerprint akhir: {$fingerprint}");

        return self::SUCCESS;
    }
}
