<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Application\Services\Scale\LedgerScaleProvisioner;
use Modules\Core\Application\Services\Scale\OutboxScaleProvisioner;
use Modules\Core\Application\Services\Scale\PassportScaleProvisioner;
use Modules\Core\Domain\Scale\ScaleProvisionerInterface;

final class ScaleProvisionCommand extends Command
{
    protected $signature = 'scale:provision
        {--module=all : Modul target provisioner (all, ledger, passport, outbox)}
        {--tier=T1 : Tier skala: T1 (demo ribuan baris) atau T2 (skala raksasa)}
        {--count= : Override target jumlah record (bawaan: T1=1000, T2=50000)}
        {--chunk=500 : Ukuran batch per bulk-insert}
        {--seed=42 : Bilangan seed deterministik}
        {--resume : Lanjutkan dari checkpoint terakhir jika tersedia}
        {--dry-run : Tampilkan rencana tanpa eksekusi database}';

    protected $description = 'Jalankan Fictional Scale Provisioner deterministik dengan chunking dan checkpoint (PROGRESS R9.1)';

    /**
     * @var array<string, class-string<ScaleProvisionerInterface>>
     */
    private array $registry = [
        'ledger' => LedgerScaleProvisioner::class,
        'passport' => PassportScaleProvisioner::class,
        'outbox' => OutboxScaleProvisioner::class,
    ];

    public function handle(): int
    {
        $targetModule = strtolower((string) $this->option('module'));
        $tier = strtoupper((string) $this->option('tier'));
        $chunk = max(1, (int) $this->option('chunk'));
        $seed = (int) $this->option('seed');
        $resume = (bool) $this->option('resume');
        $dryRun = (bool) $this->option('dry-run');

        // Target count: default T1 = 1000, T2 = 50000
        $customCount = $this->option('count');
        $targetCount = $customCount !== null
            ? max(1, (int) $customCount)
            : ($tier === 'T2' ? 50000 : 1000);

        $selectedProvisioners = [];
        if ($targetModule === 'all') {
            $selectedProvisioners = $this->registry;
        } elseif (isset($this->registry[$targetModule])) {
            $selectedProvisioners[$targetModule] = $this->registry[$targetModule];
        } else {
            $this->error("Modul '{$targetModule}' tidak dikenal. Pilihan: all, ".implode(', ', array_keys($this->registry)));

            return self::FAILURE;
        }

        $this->info('=== Fictional Scale Provisioner (Fase R9.1) ===');
        $this->line("Tier        : <comment>{$tier}</comment>");
        $this->line("Target/modul: <comment>{$targetCount}</comment> baris");
        $this->line("Chunk size  : <comment>{$chunk}</comment>");
        $this->line("Seed        : <comment>{$seed}</comment>");
        $this->line('Resume mode : <comment>'.($resume ? 'AKTIF' : 'NONAKTIF').'</comment>');
        $this->line('Dry-run     : <comment>'.($dryRun ? 'YA' : 'TIDAK').'</comment>');

        if ($dryRun) {
            $this->info('Dry-run selesai. Tidak ada data yang diinjeksi.');

            return self::SUCCESS;
        }

        $totalAll = 0;
        $t0 = hrtime(true);

        foreach ($selectedProvisioners as $key => $provisionerClass) {
            $this->line("\nMemproses provisioner: <info>{$key}</info>...");
            /** @var ScaleProvisionerInterface $provisioner */
            $provisioner = app($provisionerClass);

            $bar = $this->output->createProgressBar($targetCount);
            $bar->start();

            $inserted = $provisioner->provision(
                tier: $tier,
                targetCount: $targetCount,
                chunkSize: $chunk,
                seed: $seed,
                onProgress: function (int $current, int $total, string $message) use ($bar) {
                    $bar->setProgress($current);
                }
            );

            $bar->finish();
            $this->line("\nSelesai: <info>{$inserted}</info> record ter-provision.");
            $totalAll += $inserted;
        }

        $elapsedSec = (hrtime(true) - $t0) / 1_000_000_000;
        $throughput = $elapsedSec > 0 ? round($totalAll / $elapsedSec, 1) : $totalAll;

        $this->info("\n✅ Scale Provisioning selesai!");
        $this->line("Total record diinjeksi: <info>{$totalAll}</info>");
        $this->line('Waktu eksekusi        : <info>'.round($elapsedSec, 2).' detik</info>');
        $this->line("Throughput            : <info>{$throughput} records/detik</info>");

        return self::SUCCESS;
    }
}
