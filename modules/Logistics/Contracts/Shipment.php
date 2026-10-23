<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for Shipment model.
 */
class Shipment extends Model
{
    protected $table = 'lgx_shipments';
}
