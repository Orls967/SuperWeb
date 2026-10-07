<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DestinationPackage extends Model
{
    protected $table = 'htl_destination_packages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'package_code',
        'title',
        'total_price_idr',
        'vendor_shares',
    ];

    protected $casts = [
        'total_price_idr' => 'integer',
        'vendor_shares' => 'array',
    ];
}
