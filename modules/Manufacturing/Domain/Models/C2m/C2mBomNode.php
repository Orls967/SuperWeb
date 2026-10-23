<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models\C2m;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class C2mBomNode extends Model
{
    protected $table = 'mfg_c2m_bom_nodes';

    protected $fillable = [
        'c2m_custom_order_id',
        'node_code',
        'parent_node_code',
        'part_name',
        'material',
        'quantity',
        'unit_cost_idr',
        'subtotal_cost_idr',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost_idr' => 'integer',
        'subtotal_cost_idr' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(C2mCustomOrder::class, 'c2m_custom_order_id');
    }
}
