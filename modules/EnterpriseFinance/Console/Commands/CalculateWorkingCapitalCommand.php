<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;

class CalculateWorkingCapitalCommand extends Command
{
    protected $signature = 'ef:calculate-working-capital {--period= : Periode analisis (YYYY-MM)}';

    protected $description = 'Menghitung siklus konversi kas (CCC), DSO, DIO, dan DPO per entitas anak (54.5)';

    public function handle(EnterpriseFinanceService $service): int
    {
        $period = (string) ($this->option('period') ?: date('Y-m'));

        $this->info("Menghitung metrik modal kerja grup untuk periode {$period}...");

        $entities = [
            [
                'code' => 'ENT_LOGISTICS',
                'rev' => 120_000_000_000,
                'cogs' => 80_000_000_000,
                'ar' => 15_000_000_000,
                'inv' => 8_000_000_000,
                'ap' => 12_000_000_000,
            ],
            [
                'code' => 'ENT_RETAIL',
                'rev' => 200_000_000_000,
                'cogs' => 140_000_000_000,
                'ar' => 10_000_000_000,
                'inv' => 35_000_000_000,
                'ap' => 25_000_000_000,
            ],
        ];

        foreach ($entities as $e) {
            $metric = $service->calculateWorkingCapital(
                entityCode: $e['code'],
                period: $period,
                revenueMinor: $e['rev'],
                cogsMinor: $e['cogs'],
                arBalanceMinor: $e['ar'],
                inventoryBalanceMinor: $e['inv'],
                apBalanceMinor: $e['ap']
            );

            $this->line("Entitas [{$metric->entity_code}] DSO: {$metric->dso_days} hari, DIO: {$metric->dio_days} hari, DPO: {$metric->dpo_days} hari -> CCC: {$metric->ccc_days} hari ({$metric->efficiency_tier})");
        }

        $this->info('Perhitungan metrik modal kerja berhasil disimpan.');

        return self::SUCCESS;
    }
}
