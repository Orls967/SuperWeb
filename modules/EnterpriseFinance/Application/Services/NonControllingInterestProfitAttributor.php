<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use InvalidArgumentException;

/**
 * Pengalokasi Laba & Ekuitas Kepentingan Non-Pengendali (NCI Attributor) (54.2 - PSAK 65).
 * Menghitung pembagian laba tahun berjalan dan aset bersih ekuitas konsolidasian
 * kepada pemilik entitas induk vs pemegang saham minoritas (Non-Controlling Interest).
 */
class NonControllingInterestProfitAttributor
{
    /**
     * Menghitung atribusi laba bersih dan ekuitas untuk induk dan NCI.
     *
     * @param  int  $nciOwnershipPermille  Porsi saham minoritas dalam permille (mis. 200 = 20%)
     * @return array{
     *     subsidiary_entity: string,
     *     nci_ownership_permille: int,
     *     parent_ownership_permille: int,
     *     subsidiary_net_income_idr: int,
     *     parent_profit_share_idr: int,
     *     nci_profit_share_idr: int,
     *     subsidiary_net_assets_idr: int,
     *     parent_equity_share_idr: int,
     *     nci_equity_share_idr: int,
     *     is_zero_sum_attributed: bool
     * }
     */
    public function attributeNci(
        string $subsidiaryEntity,
        int $subsidiaryNetIncomeIdr,
        int $subsidiaryNetAssetsIdr,
        int $nciOwnershipPermille
    ): array {
        if ($nciOwnershipPermille < 0 || $nciOwnershipPermille > 1000) {
            throw new InvalidArgumentException("Proporsi kepemilikan NCI ({$nciOwnershipPermille}‰) harus berada dalam rentang 0 s/d 1000‰.");
        }

        $parentOwnershipPermille = 1000 - $nciOwnershipPermille;

        // 1. Alokasi Laba Bersih
        $nciProfitShare = (int) intdiv($subsidiaryNetIncomeIdr * $nciOwnershipPermille, 1000);
        $parentProfitShare = $subsidiaryNetIncomeIdr - $nciProfitShare;

        // 2. Alokasi Nilai Buku Aset Bersih (Ekuitas)
        $nciEquityShare = (int) intdiv($subsidiaryNetAssetsIdr * $nciOwnershipPermille, 1000);
        $parentEquityShare = $subsidiaryNetAssetsIdr - $nciEquityShare;

        $incomeBalanced = (($nciProfitShare + $parentProfitShare) === $subsidiaryNetIncomeIdr);
        $equityBalanced = (($nciEquityShare + $parentEquityShare) === $subsidiaryNetAssetsIdr);

        return [
            'subsidiary_entity' => $subsidiaryEntity,
            'nci_ownership_permille' => $nciOwnershipPermille,
            'parent_ownership_permille' => $parentOwnershipPermille,
            'subsidiary_net_income_idr' => $subsidiaryNetIncomeIdr,
            'parent_profit_share_idr' => $parentProfitShare,
            'nci_profit_share_idr' => $nciProfitShare,
            'subsidiary_net_assets_idr' => $subsidiaryNetAssetsIdr,
            'parent_equity_share_idr' => $parentEquityShare,
            'nci_equity_share_idr' => $nciEquityShare,
            'is_zero_sum_attributed' => ($incomeBalanced && $equityBalanced),
        ];
    }
}
