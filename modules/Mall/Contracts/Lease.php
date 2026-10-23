<?php

declare(strict_types=1);

namespace Modules\Mall\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for Lease model.
 */
class Lease extends Model
{
    protected $table = 'mall_leases';
}
