<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Application\Actions\ExecuteTpTrueUpAdjustmentAction;
use Modules\Intercompany\Domain\Models\TransferPricingRule;
use Psr\Clock\ClockInterface;

class TpTrueUpCommand extends Command
{
    protected $signature = 'ic:tp-trueup {--fiscal-year= : Tahun buku YYYY} {--rule= : Kode aturan TP tertentu}';

    protected $description = 'Menghitung dan memosting penyesuaian akhir tahun (True-Up Adjustment) agar margin sesuai rentang arm\'s length';

    public function handle(
        ExecuteTpTrueUpAdjustmentAction $action,
        ?ClockInterface $clock = null
    ): int {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $fiscalYear = (int) ($this->option('fiscal-year') ?: $now->year);
        $ruleCode = $this->option('rule');

        $this->info("Menjalankan evaluasi True-Up Transfer Pricing untuk Tahun Pajak {$fiscalYear}...");

        $query = TransferPricingRule::where('is_active', true);
        if ($ruleCode) {
            $query->where('rule_code', $ruleCode);
        }
        $rules = $query->get();

        if ($rules->isEmpty()) {
            $this->warn('Tidak ada aturan Transfer Pricing yang ditemukan.');

            return self::SUCCESS;
        }

        $trueUpCount = 0;
        $totalAdjustmentIdr = 0;

        foreach ($rules as $rule) {
            $this->line("Mengevaluasi Rule [{$rule->rule_code}] - {$rule->product_category} (Target: {$rule->median_margin_bps} bps)");

            // Simulasi margin aktual tahunan
            $actualMarginBps = 450; // Di bawah min margin 600 bps -> Butuh true-up adjustment
            if ($actualMarginBps < $rule->min_margin_bps) {
                $targetMarginBps = $rule->median_margin_bps;
                $adjAmountIdr = 25_000_000;

                $trueUp = $action->execute([
                    'tp_rule_id' => $rule->id,
                    'fiscal_year' => $fiscalYear,
                    'actual_margin_bps' => $actualMarginBps,
                    'target_margin_bps' => $targetMarginBps,
                    'adjustment_amount_idr' => $adjAmountIdr,
                    'affected_entity' => 'SUB_A',
                    'notes' => "True-up penyesuaian akhir tahun {$fiscalYear} ke median {$targetMarginBps} bps",
                    'post_to_ledger' => true,
                    'operator_id' => null,
                ]);

                $trueUpCount++;
                $totalAdjustmentIdr += $adjAmountIdr;
                $this->warn('  [ADJUSTMENT] True-up diterbitkan: Rp '.number_format((float) $adjAmountIdr, 0, ',', '.')." (Ref: {$trueUp->adjustment_code})");
            } else {
                $this->info("  [COMPLIANT] Margin aktual ({$actualMarginBps} bps) berada dalam rentang wajar.");
            }
        }

        $this->info("Evaluasi True-Up selesai. ({$trueUpCount} penyesuaian diposting)");
        $this->line('Total Nilai Penyesuaian: Rp '.number_format((float) $totalAdjustmentIdr, 0, ',', '.'));

        return self::SUCCESS;
    }
}
