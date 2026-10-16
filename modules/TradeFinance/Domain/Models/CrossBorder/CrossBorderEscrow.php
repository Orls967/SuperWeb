<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Domain\Models\CrossBorder;

use Illuminate\Database\Eloquent\Model;

class CrossBorderEscrow extends Model
{
    protected $table = 'tf_crossborder_escrows';

    protected $fillable = [
        'escrow_code',
        'importer_id',
        'exporter_id',
        'stablecoin_asset',
        'amount_cents',
        'locked_fx_rate_idr',
        'bl_number',
        'bl_document_hash',
        'pod_chain_hash',
        'status',
        'released_at',
    ];

    protected $casts = [
        'released_at' => 'datetime',
    ];
}
