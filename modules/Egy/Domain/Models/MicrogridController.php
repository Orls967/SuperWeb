<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MicrogridController extends Model
{
    protected $table = 'egy_microgrid_controllers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'available_power_kw' => 'float',
    ];

    public function loads()
    {
        return $this->hasMany(MicrogridLoadPriority::class, 'microgrid_id');
    }
}
