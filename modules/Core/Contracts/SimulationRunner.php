<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface SimulationRunner
{
    /**
     * Memulai sesi simulasi baru.
     *
     * @param  string  $name  Nama sesi simulasi
     * @param  int  $days  Jumlah hari virtual yang disimulasikan
     * @param  int  $seed  Seed deterministik
     * @param  string  $mode  Mode eksekusi: 'live' atau 'sandbox'
     * @return string|int ID unik dari SimRun yang dijalankan
     */
    public function start(string $name, int $days, int $seed, string $mode): string|int;

    /**
     * Melanjutkan sesi simulasi yang dijeda atau terputus dari checkpoint terakhir.
     *
     * @param  string|int  $runId  ID atau nama sesi simulasi
     * @param  int|null  $additionalDays  Jumlah hari tambahan yang disimulasikan
     */
    public function resume(string|int $runId, ?int $additionalDays = null): void;

    /**
     * Mengambil fingerprint rantai kriptografis terbaru dari sesi simulasi.
     *
     * @param  string|int  $runId  ID atau nama sesi simulasi
     * @return string Hash rantai SHA-256 (64 heksadesimal)
     */
    public function fingerprint(string|int $runId): string;
}
