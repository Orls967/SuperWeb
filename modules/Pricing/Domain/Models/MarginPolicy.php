<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Kebijakan margin minimum per channel/SKU (44.5). */
class MarginPolicy extends Model
{
    protected $table = 'pric_margin_policies';

    protected $fillable = ['channel', 'sku', 'min_margin_percent', 'floor_cost_idr', 'is_active'];

    protected $casts = [
        'min_margin_percent' => 'decimal:4', 'floor_cost_idr' => 'integer', 'is_active' => 'boolean',
    ];

    /** Harga minimum yang diizinkan dari floor cost + margin. */
    public function minAllowedPrice(): int
    {
        $cost = (int) $this->floor_cost_idr;
        $margin = (float) $this->min_margin_percent;

        return (int) ceil($cost * (100 + $margin) / 100);
    }
}
