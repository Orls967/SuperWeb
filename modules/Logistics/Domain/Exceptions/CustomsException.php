<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Exceptions;

use RuntimeException;

class CustomsException extends RuntimeException
{
    public static function forbidden(string $what): self
    {
        return new self("Anda tidak berwenang {$what}.");
    }

    public static function unknownHs(string $hsCode): self
    {
        return new self("Kode HS {$hsCode} tidak ada dalam tabel tarif.");
    }

    public static function invalidLines(): self
    {
        return new self('Minimal satu baris barang dengan kode HS 8 digit dan nilai pabean lebih dari nol.');
    }

    public static function duplicate(string $tracking, string $type): self
    {
        return new self("Resi {$tracking} sudah memiliki dokumen {$type} yang masih berjalan.");
    }

    public static function notPaid(): self
    {
        return new self('Bea dan pajak belum dibayar; dokumen tidak dapat diselesaikan (clearance).');
    }

    public static function invalidState(string $status, string $needed): self
    {
        return new self("Dokumen berstatus '{$status}'; tindakan ini membutuhkan status '{$needed}'.");
    }

    public static function shipmentNotEligible(string $tracking, string $statusLabel): self
    {
        return new self("Resi {$tracking} berstatus '{$statusLabel}' dan tidak dapat diproses kepabeanan.");
    }
}
