<?php

declare(strict_types=1);

namespace Modules\Syariah\Contracts;

/**
 * Kontrak G7-K7: SyariahWalletPayment
 *
 * Antarmuka pembayaran dompet syariah universal lintas 30 lini bisnis super-app.
 */
interface SyariahWalletPayment
{
    /**
     * Memproses pembayaran dompet digital syariah untuk merchant lintas 30 lini.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function pay(
        string $customerAccountRef,
        string $merchantCode,
        string $businessLineCode,
        int $amountMinor,
        string $orderReference,
        array $metadata = []
    ): SyariahPaymentResult;

    /**
     * Memverifikasi kepatuhan sertifikat halal merchant sebelum transaksi.
     */
    public function verifyHalalStatus(string $merchantCode, string $businessLineCode): bool;
}
