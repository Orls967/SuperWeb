<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CargoQualityDispute extends Model
{
    protected $table = 'min_cargo_quality_disputes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'seller_grade_pct' => 'float',
        'buyer_grade_pct' => 'float',
        'grade_tolerance_pct' => 'float',
        'is_payment_held' => 'boolean',
    ];
}
