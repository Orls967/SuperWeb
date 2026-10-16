<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models\Autonomous;

use Illuminate\Database\Eloquent\Model;

class TempBreachHold extends Model
{
    protected $table = 'lgx_temp_breach_holds';

    protected $guarded = [];
}
