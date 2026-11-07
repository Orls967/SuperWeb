<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\PlanMineBlockAction;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningPlan;
use Modules\Mining\Domain\Models\MiningSite;

class PhasePlanCommand extends Command
{
    protected $signature = 'mining:phase-plan {month : Format YYYY-MM}';

    protected $description = 'Melakukan time-phasing bulanan rencana tambang ke alokasi harian dan shift (idempoten)';

    public function handle(PlanMineBlockAction $action): int
    {
        $month = (string) $this->argument('month');
        $this->info("Menjalankan time-phasing rencana tambang untuk periode {$month}...");

        $sites = MiningSite::all();
        if ($sites->isEmpty()) {
            $this->warn('Tidak ada site tambang terdaftar.');

            return 0;
        }

        $count = 0;
        foreach ($sites as $site) {
            $pits = MiningPit::where('site_id', $site->id)->get();
            foreach ($pits as $pit) {
                $planCode = "PLAN-{$site->site_code}-{$pit->pit_code}-{$month}";
                if (MiningPlan::where('plan_code', $planCode)->exists()) {
                    $this->line("Rencana {$planCode} sudah ada, melewati pembuatan baru (idempoten).");

                    continue;
                }

                $action->execute([
                    'site_id' => (string) $site->id,
                    'pit_id' => (string) $pit->id,
                    'period_month' => $month,
                    'commodity' => (string) $site->commodity,
                    'target_tonnage_kg' => 100003000,
                    'target_grade_ppm' => 5000,
                ]);
                $count++;
            }
        }

        $this->info("Time-phasing selesai: {$count} rencana baru diproses.");

        return 0;
    }
}

