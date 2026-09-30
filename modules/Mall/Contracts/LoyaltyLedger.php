<?php

declare(strict_types=1);

namespace Modules\Mall\Contracts;

use App\Models\User;

interface LoyaltyLedger
{
    /**
     * Dapatkan saldo Duta Points (PTS) user saat ini.
     */
    public function getPointsBalance(User $user): int;

    /**
     * Berikan Duta Points ke user atas transaksi belanja di outlet tenant (mis. Resto POS / Store).
     * Secara otomatis mencatat double-entry PTS: debit user points, kredit liability mall points, dan FIFO batch.
     */
    public function awardPoints(
        User $user,
        int $spendAmount,
        string $receiptNumber,
        ?string $tenantExternalRef = null,
        ?User $processor = null
    ): int;

    /**
     * Terapkan dan gunakan voucher Duta Mall pada transaksi tenant.
     * Mengembalikan nominal potongan voucher dalam Rupiah (IDR).
     */
    public function applyVoucher(
        string $voucherCode,
        string $tenantExternalRef,
        int $spendAmount,
        ?string $transactionRef = null
    ): int;

    /**
     * Tukarkan Duta Points (PTS) untuk diskon langsung pada transaksi (mis. Store / Resto).
     * Rasio standar: 1 PTS = Rp 100 (mis. 100 PTS = Rp 10.000).
     */
    public function redeemPointsForDiscount(
        User $user,
        int $pointsToRedeem,
        string $description,
        ?string $referenceId = null
    ): int;
}
