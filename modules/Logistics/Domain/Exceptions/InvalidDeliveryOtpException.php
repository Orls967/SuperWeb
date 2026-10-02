<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class InvalidDeliveryOtpException extends RuntimeException
{
    public static function mismatch(): self
    {
        return new self('Kode OTP pengantaran salah. Minta penerima memeriksa kembali kode 6 digit.');
    }

    public static function locked(): self
    {
        return new self('Terlalu banyak percobaan OTP yang salah untuk resi ini. Coba lagi dalam 1 jam atau hubungi dispatcher.');
    }
}
