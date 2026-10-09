<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TransferPricingRule extends Model
{
    protected $table = 'ic_transfer_pricing_rules';

    protected $guarded = [];

    protected $casts = [
        'min_arms_length_margin_percent' => 'float',
        'max_arms_length_margin_percent' => 'float',
        'is_active' => 'boolean',
    ];
}
