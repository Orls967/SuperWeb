<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceProduct extends Model
{
    protected $table = 'ins_products';

    protected $fillable = [
        'product_code',
        'name',
        'trigger_event_type',
        'premium_amount_idr',
        'max_payout_idr',
        'is_embedded',
    ];

    protected $casts = [
        'premium_amount_idr' => 'integer',
        'max_payout_idr' => 'integer',
        'is_embedded' => 'boolean',
    ];
}
