<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnrealizedInventoryProfit extends Model
{
    protected $table = 'ic_unrealized_inventory_profits';

    protected $fillable = [
        'period',
        'selling_entity',
        'buying_entity',
        'selling_entity_id',
        'buying_entity_id',
        'inventory_balance_idr',
        'unrealized_profit_ratio_permille',
        'unrealized_profit_idr',
        'status',
        'elimination_entry_id',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'inventory_balance_idr' => 'integer',
        'unrealized_profit_ratio_permille' => 'integer',
        'unrealized_profit_idr' => 'integer',
    ];

    public function sellingEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'selling_entity_id');
    }

    public function buyingEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'buying_entity_id');
    }
}
