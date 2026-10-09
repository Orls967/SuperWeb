<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreAutonomousOperation extends Model
{
    protected $table = 'core_autonomous_operations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'autonomy_level' => 'integer',
        'kill_switch_active' => 'boolean',
        'kill_switched_at' => 'datetime',
    ];
}
