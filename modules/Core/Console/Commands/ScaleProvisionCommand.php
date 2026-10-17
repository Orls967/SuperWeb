<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ScaleProvisionerInterface;

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

    public function handle(): int
    {
        $targetModule = strtolower((string) $this->option('module'));
        $tier = strtoupper((string) $this->option('tier'));
        $chunk = max(1, (int) $this->option('chunk'));
        $seed = (int) $this->option('seed');
        $resume = (bool) $this->option('resume');
        $dryRun = (bool) $this->option('dry-run');

        $registered = [];
        foreach (app()->tagged('scale.provisioner') as $prov) {
            if ($prov instanceof ScaleProvisionerInterface) {
                $registered[$prov->moduleName()] = $prov;
            }
        }

        // Target count: default T1 = 1000, T2 = 50000
        $customCount = $this->option('count');
        $targetCount = $customCount !== null
            ? max(1, (int) $customCount)
            : ($tier === 'T2' ? 50000 : 1000);

        $selectedProvisioners = [];
        if ($targetModule === 'all') {
            $selectedProvisioners = $registered;
        } elseif (isset($registered[$targetModule])) {
            $selectedProvisioners[$targetModule] = $registered[$targetModule];
        } else {
            $this->error("Modul '{$targetModule}' tidak dikenal. Pilihan: all, ".implode(', ', array_keys($registered)));

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
            $provisioner = $provisionerClass instanceof ScaleProvisionerInterface
                ? $provisionerClass
                : app($provisionerClass);

            $checkpoint = DB::table('sim_scale_checkpoints')
                ->where('provisioner_key', $key)
                ->where('tier', $tier)
                ->where('seed', $seed)
                ->first();

            $startStep = 0;
            if ($checkpoint !== null && $resume) {
                $startStep = (int) $checkpoint->last_processed_step;
            } elseif ($checkpoint === null) {
                DB::table('sim_scale_checkpoints')->insert([
                    'provisioner_key' => $key,
                    'tier' => $tier,
                    'seed' => $seed,
                    'last_processed_step' => 0,
                    'total_inserted' => 0,
                    'status' => 'in_progress',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $bar = $this->output->createProgressBar($targetCount);
            $bar->start();
            if ($startStep > 0) {
                $bar->setProgress($startStep);
            }

            $inserted = $provisioner->provision(
                tier: $tier,
                targetCount: $targetCount,
                chunkSize: $chunk,
                seed: $seed,
                startStep: $startStep,
                onProgress: function (int $current, int $total, string $message) use ($bar, $key, $tier, $seed) {
                    $bar->setProgress($current);
                    DB::table('sim_scale_checkpoints')
                        ->where('provisioner_key', $key)
                        ->where('tier', $tier)
                        ->where('seed', $seed)
                        ->update([
                            'last_processed_step' => $current,
                            'total_inserted' => $current,
                            'status' => ($current >= $total) ? 'completed' : 'in_progress',
                            'updated_at' => now(),
                        ]);
                }
            );

            DB::table('sim_scale_checkpoints')
                ->where('provisioner_key', $key)
                ->where('tier', $tier)
                ->where('seed', $seed)
                ->update([
                    'last_processed_step' => $inserted,
                    'total_inserted' => $inserted,
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);

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
