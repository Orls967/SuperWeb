<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class HotelProperty extends Model
{
    protected $table = 'htl_properties';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'property_code',
        'name',
        'property_type',
        'city',
        'total_rooms',
    ];
}
