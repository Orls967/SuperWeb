<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmbedProduct extends Model
{
    protected $table = 'ins_embed_products';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'line_domain',
        'name',
        'coverage_type',
        'default_sum_insured_idr',
        'premium_rate_bps',
        'min_premium_idr',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'default_sum_insured_idr' => 'integer',
            'premium_rate_bps' => 'integer',
            'min_premium_idr' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<EmbedPolicy, $this> */
    public function policies(): HasMany
    {
        return $this->hasMany(EmbedPolicy::class, 'embedded_product_id');
    }
}
