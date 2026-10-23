<?php

declare(strict_types=1);

namespace Modules\Inventory\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for StockMovement model.
 */
class StockMovement extends Model
{
    public $timestamps = false;

    protected $table = 'inv_stock_movements';
}
