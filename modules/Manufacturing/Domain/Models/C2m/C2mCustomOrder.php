<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models\C2m;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class C2mCustomOrder extends Model
{
    protected $table = 'mfg_c2m_custom_orders';

    protected $fillable = [
        'c2m_code',
        'user_id',
        'base_model',
        'parameters',
        'complexity_factor',
        'rolled_up_bom_cost_idr',
        'final_price_idr',
        'status',
        'spk_number',
        'estimated_lead_days',
    ];

    protected $casts = [
        'parameters' => 'array',
        'complexity_factor' => 'decimal:2',
        'rolled_up_bom_cost_idr' => 'integer',
        'final_price_idr' => 'integer',
        'estimated_lead_days' => 'integer',
    ];

    public function bomNodes(): HasMany
    {
        return $this->hasMany(C2mBomNode::class, 'c2m_custom_order_id');
    }
}
