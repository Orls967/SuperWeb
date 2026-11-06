<?php

declare(strict_types=1);

namespace Modules\Asset\Contracts;

/**
 * G7-K9 MaintenanceWorkOrderRequester
 *
 * Kontrak integrasi pembuatan work order pemeliharaan aset dari modul lain
 * (Energi & Utilitas / Microgrid / Storage) dengan idempotensi ketat.
 */
interface MaintenanceWorkOrderRequester
{
    /**
     * Meminta pembuatan work order pemeliharaan untuk aset tertentu.
     * Satu idempotency key menjamin hanya satu work order dibuat.
     *
     * @param  int  $assetId  ID aset di modul Asset
     * @param  string  $reasonCode  Kode alasan (e.g. PIPE_LEAK_ANOMALY, CHILLER_COP_DROP, BESS_DEGRADATION)
     * @param  string  $description  Deskripsi rincian anomali/kebutuhan inspeksi
     * @param  string  $idempotencyKey  Kunci unik pencegah duplikasi order
     * @return int ID work order yang terdaftar
     */
    public function request(
        int $assetId,
        string $reasonCode,
        string $description,
        string $idempotencyKey
    ): int;
}
