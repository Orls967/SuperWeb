<?php

declare(strict_types=1);

namespace Modules\Asset\Contracts;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for Asset model.
 */
class Asset extends Model
{
    use HasUuids;

    protected $table = 'ast_assets';
}
