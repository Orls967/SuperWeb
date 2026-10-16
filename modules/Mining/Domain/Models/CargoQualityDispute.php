<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CargoQualityDispute extends Model
{
    protected $table = 'min_cargo_quality_disputes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'dispute_number',
        'voyage_id',
        'surveyor_party_id',
        'seller_grade_pct',
        'buyer_grade_pct',
        'grade_tolerance_pct',
        'sealed_sample_code',
        'is_payment_held',
        'status',
    ];

    protected $casts = [
        'seller_grade_pct' => 'float',
        'buyer_grade_pct' => 'float',
        'grade_tolerance_pct' => 'float',
        'is_payment_held' => 'boolean',
    ];
}
