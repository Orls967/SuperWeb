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
}
