<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InsuranceProductPenuh extends Model
{
    protected $table = 'ins_full_products';

    protected $fillable = [
        'product_code',
        'name',
        'line_category',
        'base_rate_bps',
        'min_premium_idr',
        'deductible_bps',
        'quota_share_pct',
        'is_active',
    ];

    protected $casts = [
        'base_rate_bps' => 'integer',
        'min_premium_idr' => 'integer',
        'deductible_bps' => 'integer',
        'quota_share_pct' => 'integer',
        'is_active' => 'boolean',
    ];

    public function policies(): HasMany
    {
        return $this->hasMany(InsurancePolicyPenuh::class, 'product_id');
    }
}
