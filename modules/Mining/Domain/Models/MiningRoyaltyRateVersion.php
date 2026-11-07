<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MiningRoyaltyRateVersion extends Model
{
    use HasUuids;

    protected $table = 'min_royalty_rate_versions';

    protected $fillable = [
        'id',
        'version_tag',
        'version',
        'commodity',
        'product_type',
        'mining_method',
        'calorific_min_kcal',
        'calorific_max_kcal',
        'price_tier_min_usd_cents',
        'price_tier_max_usd_cents',
        'rate_bps',
        'effective_from',
        'effective_to',
        'source',
        'label',
        'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'calorific_min_kcal' => 'integer',
        'calorific_max_kcal' => 'integer',
        'price_tier_min_usd_cents' => 'integer',
        'price_tier_max_usd_cents' => 'integer',
        'rate_bps' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function appliesToDate(?CarbonImmutable $date = null): bool
    {
        $date = $date ?? CarbonImmutable::now();

        if ($date->lessThan($this->effective_from)) {
            return false;
        }

        if ($this->effective_to && $date->greaterThan($this->effective_to)) {
            return false;
        }

        return $this->is_active ?? true;
    }
}
