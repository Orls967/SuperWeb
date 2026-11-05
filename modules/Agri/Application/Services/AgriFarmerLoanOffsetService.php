<?php

declare(strict_types=1);

namespace Modules\Agri\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Agri\Contracts\FarmerLoanOffset;

final class AgriFarmerLoanOffsetService implements FarmerLoanOffset
{
    /**
     * Memotong saldo pinjaman petani plasma dengan santunan klaim asuransi pertanian.
     */
    public function offsetLoanBalance(string $farmerPartyId, int $offsetAmountIdr, string $referenceClaimNumber): int
    {
        return DB::transaction(function () use ($farmerPartyId, $offsetAmountIdr) {
            $currentLoan = $this->getOutstandingLoanBalance($farmerPartyId);

            return max(0, $currentLoan - $offsetAmountIdr);
        });
    }

    /**
     * Mengambil saldo pinjaman simulasi petani plasma.
     */
    public function getOutstandingLoanBalance(string $farmerPartyId): int
    {
        // Simulasi limit pinjaman plasma standar (Rp 20.000.000 = 20.000.000 sen)
        return 20000000;
    }
}
