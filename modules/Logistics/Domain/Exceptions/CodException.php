<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class CodException extends RuntimeException
{
    public static function notCollected(string $trackingNumber, int $amount): self
    {
        return new self("Resi {$trackingNumber} adalah COD Rp ".number_format($amount, 0, ',', '.').'; konfirmasi penerimaan uang tunai dari penerima sebelum menyelesaikan pengantaran.');
    }

    public static function depositMismatch(int $expected, int $given): self
    {
        return new self('Setoran COD tidak sesuai: seharusnya Rp '.number_format($expected, 0, ',', '.').', diterima Rp '.number_format($given, 0, ',', '.').'. Hitung ulang uang tunai driver.');
    }

    public static function nothingToDeposit(): self
    {
        return new self('Driver tidak memiliki uang COD yang perlu disetor.');
    }

    public static function wrongHub(): self
    {
        return new self('Anda hanya dapat menerima setoran COD di hub penugasan Anda.');
    }
}
