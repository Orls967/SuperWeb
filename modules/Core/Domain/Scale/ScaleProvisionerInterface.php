<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Scale;

interface ScaleProvisionerInterface
{
    /**
     * Nama unik modul/pilar yang ditangani oleh provisioner.
     */
    public function moduleName(): string;

    /**
     * Eksekusi injeksi data berskala deterministik.
     *
     * @param  string  $tier  'T1' (demo ribuan baris) atau 'T2' (skala raksasa)
     * @param  int  $targetCount  Target total jumlah record
     * @param  int  $chunkSize  Ukuran batch per bulk-insert
     * @param  int  $seed  Bilangan seed deterministik untuk generator
     * @param  (callable(int $current, int $total, string $message): void)|null  $onProgress
     * @return int Jumlah total record yang berhasil di-provision
     */
    public function provision(
        string $tier,
        int $targetCount,
        int $chunkSize,
        int $seed,
        ?callable $onProgress = null
    ): int;
}
