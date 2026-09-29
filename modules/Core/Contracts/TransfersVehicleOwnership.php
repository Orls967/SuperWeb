<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Kontrak publik Core untuk memindahkan kepemilikan kendaraan antar pengguna.
 * Dipakai modul lain (Store C2C, Finance) tanpa menyentuh Domain Core.
 */
interface TransfersVehicleOwnership
{
    /**
     * Pindahkan kepemilikan kendaraan ke pemilik baru dan catat blok
     * `ownership_transferred` pada paspor digital kendaraan.
     *
     * @param  object|int  $vehicle  Model Vehicle atau ID-nya
     * @return object Vehicle yang sudah berpindah tangan
     */
    public function handle(
        object|int $vehicle,
        int $toUserId,
        string $viaType = 'manual',
        ?int $viaId = null,
        ?int $priceIdr = null,
        ?int $actorId = null,
    ): object;
}
