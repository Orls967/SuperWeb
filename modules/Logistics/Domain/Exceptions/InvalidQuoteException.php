<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use DomainException;

class InvalidQuoteException extends DomainException
{
    public static function expired(): self
    {
        return new self('Quote tarif telah kedaluwarsa (batas waktu 15 menit telah terlewati). Silakan ajukan quote baru.');
    }

    public static function tampered(): self
    {
        return new self('Quote tidak valid atau rincian tarif telah dimanipulasi (verifikasi hash gagal).');
    }

    public static function alreadyBooked(): self
    {
        return new self('Quote ini telah digunakan untuk pemesanan sebelumnya.');
    }
}
