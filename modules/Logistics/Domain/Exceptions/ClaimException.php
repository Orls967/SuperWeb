<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class ClaimException extends RuntimeException
{
    public static function forbidden(string $what): self
    {
        return new self("Anda tidak berwenang {$what}.");
    }

    public static function invalidState(string $status, string $needed): self
    {
        return new self("Klaim berstatus '{$status}'; tindakan ini membutuhkan status '{$needed}'.");
    }

    public static function ineligibleShipment(string $tracking, string $type, string $statusLabel): self
    {
        return new self("Resi {$tracking} berstatus '{$statusLabel}' dan tidak memenuhi syarat klaim jenis '{$type}'.");
    }

    public static function windowExpired(int $days): self
    {
        return new self("Masa pengajuan klaim ({$days} hari sejak kejadian) telah lewat.");
    }

    public static function duplicate(string $tracking): self
    {
        return new self("Resi {$tracking} sudah memiliki klaim aktif; satu resi hanya boleh memiliki satu klaim yang tidak ditolak.");
    }

    public static function exceedsCap(int $amount, int $cap): self
    {
        return new self('Nilai klaim Rp '.number_format($amount, 0, ',', '.').' melebihi batas ganti rugi Rp '.number_format($cap, 0, ',', '.').'.');
    }

    public static function fourEyes(string $detail): self
    {
        return new self("Aturan 4 mata: {$detail}");
    }

    public static function noBasis(): self
    {
        return new self('Klaim barang asuransi memerlukan nilai barang yang dideklarasikan pada resi.');
    }
}
