<?php

declare(strict_types=1);

namespace Modules\AutoDex\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for Brand model.
 */
class Brand extends Model
{
    protected $table = 'dex_brands';
}
