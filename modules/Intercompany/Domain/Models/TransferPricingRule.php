<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferPricingRule extends Model
{
    protected $table = 'ic_transfer_pricing_rules';

    protected $fillable = [
        'rule_code',
        'product_category',
        'tp_method',
        'min_arms_length_margin_percent',
        'max_arms_length_margin_percent',
        'min_margin_bps',
        'max_margin_bps',
        'median_margin_bps',
        'benchmark_industry_source',
        'is_active',
    ];

    protected $casts = [
        'min_margin_bps' => 'integer',
        'max_margin_bps' => 'integer',
        'median_margin_bps' => 'integer',
        'is_active' => 'boolean',
    ];

    public function benchmarks(): HasMany
    {
        return $this->hasMany(TransferPricingBenchmark::class, 'rule_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(IntercompanyTransaction::class, 'tp_rule_id');
    }
}
