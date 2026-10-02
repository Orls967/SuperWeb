<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class DemurrageException extends RuntimeException
{
    public static function noTariff(string $kind, string $location): self
    {
        return new self("Tarif {$kind} untuk lokasi {$location} belum dikonfigurasi.");
    }

    public static function alreadyOpen(string $containerNumber, string $kind): self
    {
        return new self("Kontainer {$containerNumber} sudah memiliki hitungan {$kind} yang berjalan.");
    }

    public static function alreadyClosed(): self
    {
        return new self('Hitungan ini sudah ditutup.');
    }

    public static function invalidTime(): self
    {
        return new self('Waktu selesai tidak boleh lebih awal dari waktu mulai.');
    }
}
