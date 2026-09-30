<?php

declare(strict_types=1);

namespace Modules\Mall\Contracts;

use Modules\Mall\Domain\Exceptions\InvalidParkingTicketException;

/**
 * Dipakai modul tenant (mis. POS Resto) untuk menanggung tarif parkir pelanggan
 * setelah belanja mencapai ambang minimal pada kontrak sewanya.
 */
interface ParkingValidator
{
    /**
     * @param  string  $tenantExternalRef  Referensi tenant pada kontrak sewa (mis. kode outlet resto)
     * @param  int  $spendAmount  Nominal belanja pelanggan dalam rupiah
     *
     * @throws InvalidParkingTicketException bila tiket/tenant tidak valid atau belanja belum mencukupi
     */
    public function validateTicket(string $ticketNumber, string $tenantExternalRef, int $spendAmount): ParkingValidationResult;

    /**
     * Berapa jam parkir yang ditanggung tenant ini untuk nominal belanja tertentu.
     * Mengembalikan 0 bila tenant tidak punya fasilitas validasi atau belanja kurang.
     */
    public function eligibleFreeHours(string $tenantExternalRef, int $spendAmount): int;
}
