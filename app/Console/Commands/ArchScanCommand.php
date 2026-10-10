<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Quality\ArchScan\ArchScanner;
use App\Quality\ArchScan\ScanReport;
use App\Quality\Baseline\BaselineComparison;
use App\Quality\Baseline\BaselineIncreaseRefused;
use App\Quality\Baseline\FingerprintBaseline;
use Illuminate\Console\Command;

/**
 * Static scan for the shortcut patterns found by the audit (KONSEP.md §A14.1).
 * Exits non-zero when a violation is not covered by the ratchet baseline or when
 * the baseline still lists violations that are gone (the baseline must go down).
 */
class ArchScanCommand extends Command
{
    public const DEFAULT_BASELINE = 'tests/Architecture/baselines/arch-scan.json';

    public const BASELINE_ABOUT = 'Baseline ratchet `php artisan arch:scan` (PROGRESS.md §P11, KONSEP.md §A14.1). Hanya boleh turun lewat `--update-baseline`; penambahan wajib `--allow-new="DECISIONS.md#…"` dan tercatat di approved_additions.';

    protected $signature = 'arch:scan
        {--json : Cetak ringkasan dan selisih terhadap baseline sebagai JSON}
        {--update-baseline : Tulis ulang baseline dari hasil pindaian (hanya boleh turun)}
        {--allow-new= : Rujukan DECISIONS.md yang mengizinkan pelanggaran baru masuk baseline}
        {--baseline='.self::DEFAULT_BASELINE.' : Path baseline relatif ke root proyek}
        {--path=* : Direktori yang dipindai relatif ke root proyek (bawaan: modules)}';

    protected $description = 'Pindai pola jalan pintas A1–A13 (KONSEP §A14.1) dan bandingkan dengan baseline ratchet';

    public function handle(): int
    {
        $paths = $this->option('path') ?: ['modules'];
        $report = ArchScanner::scanProject(base_path(), (array) config('modules'), $paths);
        $baselinePath = base_path((string) $this->option('baseline'));
        $baseline = FingerprintBaseline::fromFile($baselinePath);

        if ($this->option('update-baseline')) {
            return $this->updateBaseline($baseline, $report, $baselinePath);
        }

        $comparison = $baseline->compare($report);

        if ($this->option('json')) {
            $this->line((string) json_encode($this->summary($report, $baseline, $comparison), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $comparison->isClean() ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Aturan', 'Deskripsi', 'Sekarang', 'Baseline', 'Baru', 'Basi'],
            array_map(fn (string $ruleId): array => [
                $ruleId,
                $report->description($ruleId),
                $report->count($ruleId),
                $baseline->total($ruleId),
                $comparison->newCount($ruleId),
                $comparison->staleCount($ruleId),
            ], $report->ruleIds()),
        );

        if ($comparison->isClean()) {
            $this->info('arch:scan bersih: tidak ada pelanggaran baru dan baseline sudah mutakhir.');

            return self::SUCCESS;
        }

        $this->error($comparison->describe());

        if ($comparison->hasStale() && ! $comparison->hasNew()) {
            $this->error('Pelanggaran berkurang. Turunkan baseline di perubahan yang sama: php artisan arch:scan --update-baseline');
        }

        return self::FAILURE;
    }

    private function updateBaseline(FingerprintBaseline $baseline, ScanReport $report, string $baselinePath): int
    {
        try {
            $updated = $baseline->updatedFrom($report, $this->option('allow-new'), now()->toDateString());
        } catch (BaselineIncreaseRefused $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! is_dir(dirname($baselinePath))) {
            mkdir(dirname($baselinePath), 0755, true);
        }

        file_put_contents($baselinePath, $updated->toJson(self::BASELINE_ABOUT));

        foreach ($report->ruleIds() as $ruleId) {
            $this->line(sprintf('%-4s %6d → %6d', $ruleId, $baseline->total($ruleId), $updated->total($ruleId)));
        }

        $this->info("Baseline ditulis: {$this->option('baseline')}");

        return self::SUCCESS;
    }

    /**
     * @return array{clean: bool, rules: array<string, array{description: string, count: int, baseline: int, new: int, stale: int}>, new: array<string, mixed>, stale: array<string, mixed>}
     */
    private function summary(ScanReport $report, FingerprintBaseline $baseline, BaselineComparison $comparison): array
    {
        $rules = [];

        foreach ($report->ruleIds() as $ruleId) {
            $rules[$ruleId] = [
                'description' => $report->description($ruleId),
                'count' => $report->count($ruleId),
                'baseline' => $baseline->total($ruleId),
                'new' => $comparison->newCount($ruleId),
                'stale' => $comparison->staleCount($ruleId),
            ];
        }

        return [
            'clean' => $comparison->isClean(),
            'rules' => $rules,
            'new' => array_filter($comparison->new),
            'stale' => array_filter($comparison->stale),
        ];
    }
}
