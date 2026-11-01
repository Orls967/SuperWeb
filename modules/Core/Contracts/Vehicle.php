<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for Vehicle model.
 */
class Vehicle extends Model
{
    protected $table = 'core_vehicles';

    protected $fillable = [
        'uuid',
        'user_id',
        'car_id',
        'plate_number',
        'vin',
        'color',
        'year',
        'mileage',
        'status',
        'meta',
    ];
}
