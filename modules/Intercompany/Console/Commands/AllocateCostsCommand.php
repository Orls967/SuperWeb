<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Application\Actions\AllocateCostSharingAction;
use Modules\Intercompany\Domain\Models\CostSharingAgreement;
use Psr\Clock\ClockInterface;

class AllocateCostsCommand extends Command
{
    protected $signature = 'ic:allocate-costs {--period= : Periode alokasi biaya YYYY-MM} {--dry-run : Simulasi tanpa posting}';

    protected $description = 'Mengeksekusi alokasi biaya bersama (Cost Sharing Agreement) dan memosting tagihan jasa manajemen';

    public function handle(
        AllocateCostSharingAction $action,
        ?ClockInterface $clock = null
    ): int {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $period = $this->option('period') ?: $now->format('Y-m');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Menjalankan alokasi cost sharing untuk periode {$period}...");

        $agreements = CostSharingAgreement::where('is_active', true)->get();

        if ($agreements->isEmpty()) {
            $this->warn('Tidak ada Perjanjian Cost Sharing (CSA) yang aktif.');

            return self::SUCCESS;
        }

        $allocatedCount = 0;
        $totalAllocatedIdr = 0;

        foreach ($agreements as $agreement) {
            $this->line("Memproses CSA [{$agreement->agreement_code}] - {$agreement->service_type} dari {$agreement->provider_entity}");

            // Alokasikan untuk masing-masing entitas penerima manfaat (beneficiary)
            $beneficiaries = ['SUB_A', 'SUB_B'];
            $ratioPermille = 50000; // 50% masing-masing

            foreach ($beneficiaries as $ben) {
                if ($ben === $agreement->provider_entity) {
                    continue;
                }

                $allocatedMinor = (int) ($agreement->total_cost_pool_idr * $ratioPermille / 100000);

                if (! $dryRun) {
                    $action->execute([
                        'agreement_id' => $agreement->id,
                        'beneficiary_entity' => $ben,
                        'allocated_amount_idr' => $allocatedMinor,
                        'period' => $period,
                        'basis_ratio_permille' => $ratioPermille,
                        'post_to_ledger' => true,
                        'operator_id' => null,
                    ]);
                }

                $allocatedCount++;
                $totalAllocatedIdr += $allocatedMinor;
                $this->line("  -> Alokasi ke {$ben}: Rp ".number_format((float) $allocatedMinor, 0, ',', '.'));
            }
        }

        $this->info("Alokasi cost sharing periode {$period} selesai. ({$allocatedCount} alokasi diproses)");
        $this->line('Total Nilai Dialokasikan: Rp '.number_format((float) $totalAllocatedIdr, 0, ',', '.'));

        return self::SUCCESS;
    }
}
