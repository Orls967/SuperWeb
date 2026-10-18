<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CoreAiPricingRule extends Model
{
    protected $table = 'core_ai_pricing_rules';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'rule_code',
        'item_code',
        'item_name',
        'channel',
        'base_price_idr',
        'floor_price_idr',
        'ceiling_price_idr',
        'elasticity_coefficient',
        'max_surge_multiplier',
        'decay_hours_threshold',
        'is_active',
    ];

    protected $casts = [
        'base_price_idr' => 'integer',
        'floor_price_idr' => 'integer',
        'ceiling_price_idr' => 'integer',
        'elasticity_coefficient' => 'float',
        'max_surge_multiplier' => 'float',
        'decay_hours_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->rule_code)) {
                $model->rule_code = 'PR-'.Str::upper(Str::random(8));
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
