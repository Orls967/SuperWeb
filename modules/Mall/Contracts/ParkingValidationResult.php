<?php

declare(strict_types=1);

namespace Modules\Mall\Contracts;

/**
 * Hasil validasi parkir yang dikembalikan ke modul pemanggil (mis. POS Resto).
 *
 * Sengaja berupa DTO sederhana supaya modul lain tidak perlu menyentuh
 * Modules\Mall\Domain sama sekali.
 */
final readonly class ParkingValidationResult
{
    public function __construct(
        public string $ticketNumber,
        public string $plateNumber,
        public int $freeHours,
        public string $tenantName,
        public string $note,
    ) {}

    /**
     * Keterangan siap cetak di struk pelanggan.
     */
    public function receiptLine(): string
    {
        return "Parkir {$this->freeHours} jam pertama ditanggung {$this->tenantName} (Tiket {$this->ticketNumber})";
    }
}
