<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntercompanyAssetTransfer extends Model
{
    protected $table = 'ic_asset_transfers';

    protected $fillable = [
        'transfer_code',
        'asset_identifier',
        'asset_name',
        'transferor_entity',
        'transferee_entity',
        'source_entity_id',
        'target_entity_id',
        'net_book_value_idr',
        'book_value_idr',
        'accumulated_depreciation_idr',
        'transfer_price_idr',
        'gain_loss_idr',
        'transfer_date',
        'status',
        'notes',
        'ledger_transaction_id',
    ];

    protected $casts = [
        'net_book_value_idr' => 'integer',
        'accumulated_depreciation_idr' => 'integer',
        'transfer_price_idr' => 'integer',
        'gain_loss_idr' => 'integer',
    ];

    public function sourceEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'source_entity_id');
    }

    public function targetEntity(): BelongsTo
    {
        return $this->belongsTo(IntercompanyEntity::class, 'target_entity_id');
    }
}
