<?php

declare(strict_types=1);

namespace Modules\Supplier\Contracts;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for Supplier model.
 */
class Supplier extends Model
{
    use HasUuids;

    protected $table = 'sup_suppliers';
}
