<?php

declare(strict_types=1);

namespace Modules\Agri\Contracts;

/**
 * Kontrak Lintas Modul G7-K2: Insurance ↔ Agri.
 *
 * Mengizinkan modul Insurance memotong pinjaman mikro pembiayaan petani plasma
 * secara otomatis ketika klaim asuransi indeks cuaca/pertanian cair (offset).
 */
interface FarmerLoanOffset
{
    /**
     * Memotong saldo pinjaman petani plasma dengan santunan klaim asuransi pertanian.
     *
     * @param  string  $farmerPartyId  UUID petani
     * @param  int  $offsetAmountIdr  Jumlah klaim pemotong dalam minor unit (sen)
     * @param  string  $referenceClaimNumber  Nomor klaim asuransi pertanian
     * @return int Sisa saldo pinjaman setelah offset (0 jika lunas)
     */
    public function offsetLoanBalance(string $farmerPartyId, int $offsetAmountIdr, string $referenceClaimNumber): int;

    /**
     * Mengambil sisa saldo pinjaman petani saat ini.
     *
     * @param  string  $farmerPartyId  UUID petani
     * @return int Saldo pinjaman dalam minor unit (sen)
     */
    public function getOutstandingLoanBalance(string $farmerPartyId): int;
}
