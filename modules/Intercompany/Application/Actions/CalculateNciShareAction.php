<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\SubsidiaryNci;

class CalculateNciShareAction
{
    public function __construct(
        protected readonly IntercompanyLedgerPoster $ledgerPoster
    ) {}

    public function execute(
        string $subsidiaryName,
        float $parentSharePercent,
        int $netIncomeIdr,
        string $period = '2026-10',
        ?int $userId = null
    ): SubsidiaryNci {
        if ($parentSharePercent < 0.0 || $parentSharePercent > 100.0) {
            throw new InvalidArgumentException('Persentase kepemilikan induk harus antara 0% dan 100%.');
        }

        $nciSharePercent = 100.0 - $parentSharePercent;
        $parentPermille = (int) round($parentSharePercent * 1000);
        $nciPermille = 100000 - $parentPermille;

        $nciIncome = (int) floor(($netIncomeIdr * $nciPermille) / 100000);

        $entity = IntercompanyEntity::where('entity_code', $subsidiaryName)->first();

        $record = SubsidiaryNci::updateOrCreate(
            ['subsidiary_name' => $subsidiaryName],
            [
                'subsidiary_entity_id' => $entity?->id,
                'parent_ownership_percent' => $parentSharePercent,
                'nci_ownership_percent' => $nciSharePercent,
                'parent_ownership_permille' => $parentPermille,
                'nci_ownership_permille' => $nciPermille,
                'net_income_idr' => $netIncomeIdr,
                'nci_share_net_income_idr' => $nciIncome,
                'period' => $period,
            ]
        );

        if ($nciIncome > 0) {
            $this->ledgerPoster->postNciProfitShare(
                subsidiaryEntity: $subsidiaryName,
                nciShareNetIncomeIdr: $nciIncome,
                period: $period,
                idempotencyKey: "IC:NCI:SHARE:{$period}:{$subsidiaryName}",
                userId: $userId
            );
        }

        return $record;
    }
}
