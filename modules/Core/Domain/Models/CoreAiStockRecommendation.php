<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CoreAiStockRecommendation extends Model
{
    protected $table = 'core_ai_stock_recommendations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'recommendation_code',
        'item_code',
        'item_name',
        'current_stock',
        'calculated_rop',
        'safety_stock',
        'suggested_order_qty',
        'seasonal_factor',
        'estimated_cost_idr',
        'requires_human_approval',
        'decision_snapshot_id',
        'status',
        'approved_po_ref',
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'calculated_rop' => 'integer',
        'safety_stock' => 'integer',
        'suggested_order_qty' => 'integer',
        'seasonal_factor' => 'float',
        'estimated_cost_idr' => 'integer',
        'requires_human_approval' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->recommendation_code)) {
                $model->recommendation_code = 'REC-'.Str::upper(Str::random(8));
            }
        });
    }

    /**
     * @return BelongsTo<CoreAiDecisionSnapshot, self>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CoreAiDecisionSnapshot::class, 'decision_snapshot_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', 'PROPOSED')->where('requires_human_approval', true);
    }
}
