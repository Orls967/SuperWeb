<?php

declare(strict_types=1);

namespace Modules\Fashion\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FashionBuyPlan extends Model
{
    protected $table = 'fsh_buy_plans';

    protected $fillable = [
        'collection_id',
        'plan_code',
        'target_total_units',
        'budget_idr',
        'status',
    ];

    protected $casts = [
        'target_total_units' => 'integer',
        'budget_idr' => 'integer',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(FashionCollection::class, 'collection_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(FashionProductionOrder::class, 'buy_plan_id');
    }
}
