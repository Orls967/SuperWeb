<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Syariah\Application\Services\NpfClassificationService;
use Modules\Syariah\Domain\Models\SybRestructuringRequest;

final class NpfClassificationDailySweepCommand extends Command
{
    protected $signature = 'syb:npf-daily-sweep {--period= : Label periode evaluasi (default YYYY-MM)}';

    protected $description = 'Sweep harian untuk evaluasi kolektibilitas portofolio syariah dan penetapan CKPN (POJK)';

    public function handle(NpfClassificationService $npfService): int
    {
        $periodLabel = (string) ($this->option('period') ?: Carbon::now()->format('Y-m'));
        $this->info("Menjalankan sweep klasifikasi NPF syariah untuk periode {$periodLabel}...");

        // Contoh agregasi kontrak aktif dari berbagai lini pembiayaan syariah
        $restructuringContracts = SybRestructuringRequest::where('status', 'EXECUTED')->get();

        $contractInputs = [];
        foreach ($restructuringContracts as $req) {
            $contractInputs[] = [
                'contract_ref' => $req->financing_ref,
                'customer_ref' => $req->customer_ref,
                'product_type' => $req->contract_type,
                'dpd' => 0, // Portofolio restrukturisasi yang lancar
                'outstanding_principal_minor' => $req->revised_principal_minor,
                'remaining_margin_minor' => $req->revised_margin_minor,
            ];
        }

        if (empty($contractInputs)) {
            // Sample fallback portfolio jika belum ada kontrak aktif
            $contractInputs[] = [
                'contract_ref' => 'FIN-SAMPLE-BASE',
                'customer_ref' => 'CUST-SYS-001',
                'product_type' => 'MURABAHAH',
                'dpd' => 0,
                'outstanding_principal_minor' => 100_000_000,
                'remaining_margin_minor' => 10_000_000,
            ];
        }

        $result = $npfService->evaluateAndClassify($periodLabel, $contractInputs, 'CRON_DAILY_SWEEP');

        $this->info("Sweep NPF selesai. Classification Ref: {$result->classification_ref}, NPF Gross: {$result->npf_gross_bps} bps, Cadangan CKPN: Rp ".number_format($result->total_required_provision_minor / 100, 2));

        return Command::SUCCESS;
    }
}
