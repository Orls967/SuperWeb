<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MicrogridLoadPriority extends Model
{
    protected $table = 'egy_microgrid_load_priorities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'priority_rank' => 'integer',
        'required_load_kw' => 'float',
        'is_shed' => 'boolean',
    ];
}
