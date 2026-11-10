<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Kontrak ConsumerInbox (Fase R8 / W8-D37)
 *
 * Menjamin idempotensi pemrosesan event per grup konsumen di tingkat database persisten,
 * tahan terhadap proses restart dan kegagalan worker.
 */
interface ConsumerInbox
{
    /**
     * Klaim pesan secara atomik. Mengembalikan true jika pesan belum pernah diproses,
     * atau false jika pesan telah diproses sebelumnya oleh grup ini.
     */
    public function claim(string $group, string $messageId, ?string $handler = null): bool;

    /**
     * Memeriksa apakah pesan telah diproses oleh grup konsumen.
     */
    public function hasProcessed(string $group, string $messageId): bool;

    /**
     * Mencatat penyelesaian sukses pemrosesan pesan beserta hash respons dan durasi.
     */
    public function recordSuccess(string $group, string $messageId, ?string $responseHash = null, int $durationMs = 0): void;
}
