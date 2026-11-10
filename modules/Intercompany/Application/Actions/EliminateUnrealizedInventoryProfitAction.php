<?php

declare(strict_types=1);

namespace Modules\Intercompany\Application\Actions;

use InvalidArgumentException;
use Modules\Intercompany\Application\Services\IntercompanyLedgerPoster;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\UnrealizedInventoryProfit;

class EliminateUnrealizedInventoryProfitAction
{
    public function __construct(
        protected readonly IntercompanyLedgerPoster $ledgerPoster
    ) {}

    /**
     * @param array{
     *     period: string,
     *     selling_entity: string,
     *     buying_entity: string,
     *     product_code: string,
     *     inventory_balance_idr: int,
     *     unrealized_profit_ratio_permille: int,
     *     operator_id?: int|null
     * } $data
     */
    public function execute(array $data): UnrealizedInventoryProfit
    {
        $selling = $data['selling_entity'];
        $buying = $data['buying_entity'];
        $balance = (int) $data['inventory_balance_idr'];
        $ratio = (int) $data['unrealized_profit_ratio_permille'];

        if ($balance <= 0 || $ratio <= 0) {
            throw new InvalidArgumentException('Saldo persediaan dan rasio margin laba harus lebih besar dari nol.');
        }

        $sellerEntity = IntercompanyEntity::where('entity_code', $selling)->first();
        $buyerEntity = IntercompanyEntity::where('entity_code', $buying)->first();

        $unrealizedProfit = (int) floor(($balance * $ratio) / 100000);

        $record = UnrealizedInventoryProfit::create([
            'period' => $data['period'],
            'selling_entity_id' => $sellerEntity?->id,
            'buying_entity_id' => $buyerEntity?->id,
            'product_code' => $data['product_code'],
            'inventory_balance_idr' => $balance,
            'unrealized_profit_ratio_permille' => $ratio,
            'unrealized_profit_idr' => $unrealizedProfit,
            'status' => 'eliminated',
        ]);

        $ledgerTx = $this->ledgerPoster->postUnrealizedInventoryProfitElimination(
            sellingEntity: $selling,
            buyingEntity: $buying,
            unrealizedProfitIdr: $unrealizedProfit,
            period: $data['period'],
            idempotencyKey: "IC:UIP:{$data['period']}:{$selling}:{$buying}:{$data['product_code']}",
            userId: $data['operator_id'] ?? null
        );

        $record->ledger_transaction_id = $ledgerTx->id;
        $record->save();

        return $record;
    }
}
