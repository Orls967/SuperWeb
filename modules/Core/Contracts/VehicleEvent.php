<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for VehicleEvent model.
 */
class VehicleEvent extends Model
{
    protected $table = 'core_vehicle_events';
}
