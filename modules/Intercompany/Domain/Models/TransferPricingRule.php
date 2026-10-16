<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TransferPricingRule extends Model
{
    protected $table = 'ic_transfer_pricing_rules';

    protected $fillable = [
        'rule_code',
        'product_category',
        'tp_method',
        'min_arms_length_margin_percent',
        'max_arms_length_margin_percent',
        'benchmark_industry_source',
        'is_active',
    ];

    protected $casts = [
        'min_arms_length_margin_percent' => 'float',
        'max_arms_length_margin_percent' => 'float',
        'is_active' => 'boolean',
    ];
}
