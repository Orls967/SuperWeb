<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FestivalBundle extends Model
{
    protected $table = 'ven_festival_bundles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'bundle_code',
        'bundle_name',
        'total_package_price',
        'vendor_allocations',
        'status',
    ];

    protected $casts = [
        'total_package_price' => 'integer',
        'vendor_allocations' => 'array',
    ];
}
