<?php

declare(strict_types=1);

namespace Modules\AutoServe\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Cross-module contract for Sparepart model.
 */
class Sparepart extends Model
{
    protected $table = 'serve_spareparts';
}
