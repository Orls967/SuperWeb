<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Console\Commands;

use Illuminate\Console\Command;
use Modules\Manufacturing\Application\Services\QualityService;
use Modules\Manufacturing\Domain\Models\Capa;
use Modules\Manufacturing\Domain\Models\Gauge;
use Modules\Manufacturing\Domain\Models\Ncr;
use Modules\Manufacturing\Domain\Models\Recall;

/**
 * 39.x Audit QMS harian: alat ukur kadaluarsa, CAPA/NCR lewat tenggat,
 * recall belum tuntas. Exit 1 bila ada temuan.
 */
class QmsAuditCommand extends Command
{
    protected $signature = 'mfg:qms-audit';

    protected $description = 'Audit mutu: alat kalibrasi, CAPA/NCR, recall (exit 1 bila ada temuan)';

    public function handle(QualityService $quality): int
    {
        $quality->markOverdueCapas();

        $rows = [];
        $issues = 0;

        $expiredGauges = Gauge::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('calibration_due')->orWhere('calibration_due', '<', now());
            })->get();
        foreach ($expiredGauges as $gauge) {
            $rows[] = ['gauge:'.$gauge->code, 'kalibrasi', $gauge->calibration_due?->toDateString() ?? 'belum', 'SELISIH'];
            $issues++;
        }

        $overdueCapas = Capa::where('status', 'overdue')->with('ncr')->get();
        foreach ($overdueCapas as $capa) {
            $rows[] = ['capa:'.$capa->id, 'lewat tenggat', $capa->due_date?->toDateString() ?? '-', 'SELISIH'];
            $issues++;
        }

        $oldNcrs = Ncr::whereIn('status', ['open', 'investigating', 'capa'])
            ->where('due_date', '<', now()->toDateString())->get();
        foreach ($oldNcrs as $ncr) {
            $rows[] = ['ncr:'.$ncr->number, 'lewat tenggat ('.$ncr->status.')', $ncr->due_date?->toDateString() ?? '-', 'SELISIH'];
            $issues++;
        }

        $stuckRecalls = Recall::whereIn('status', ['planned', 'notified'])
            ->where('started_at', '<', now()->subDays(3))->get();
        foreach ($stuckRecalls as $recall) {
            $rows[] = ['recall:'.substr($recall->id, 0, 8), 'belum selesai ('.$recall->status.')', $recall->started_at?->toDateString() ?? '-', 'SELISIH'];
            $issues++;
        }

        $this->table(['Sumber', 'Temuan', 'Tanggal', 'Status'],
            $rows === [] ? [['—', 'tidak ada temuan', '-', 'OK']] : $rows);

        if ($issues > 0) {
            $this->warn("Ditemukan {$issues} temuan QMS.");

            return self::FAILURE;
        }

        $this->info('✓ mfg:qms-audit selesai: kalibrasi, CAPA/NCR, dan recall sehat.');

        return self::SUCCESS;
    }
}
