<?php

declare(strict_types=1);

namespace Modules\Treasury\Contracts;

/**
 * Kontrak antarmuka untuk modul sumber pos moneter valuta asing (Procurement, Sales, Trade, Loans).
 * Melaporkan saldo moneter valas per periode untuk revaluasi akhir periode.
 */
interface FxMonetaryItemFeed
{
    /**
     * @return iterable<array{
     *     source_module: string,
     *     source_entity_id: string,
     *     currency: string,
     *     balance_minor: int,
     *     carrying_functional_idr: int,
     *     item_type: string,
     *     is_asset: bool,
     *     lot_date: string
     * }>
     */
    public function items(string $period): iterable;
}
