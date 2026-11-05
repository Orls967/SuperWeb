<?php

declare(strict_types=1);

namespace Modules\Forest\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class ForestTimberSale extends Model
{
    protected $table = 'for_timber_sales';

    protected $fillable = [
        'sale_code',
        'buyer_party_ref',
        'total_volume_dm3',
        'total_amount_idr',
        'forestry_levy_idr',
        'idempotency_key',
        'status',
    ];

    protected $casts = [
        'buyer_party_ref' => 'integer',
        'total_volume_dm3' => 'integer',
        'total_amount_idr' => 'integer',
        'forestry_levy_idr' => 'integer',
    ];
}
