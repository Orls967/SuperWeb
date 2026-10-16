<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreAutonomousOperation extends Model
{
    protected $table = 'core_autonomous_operations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'operation_code',
        'line_code',
        'autonomy_level',
        'action_name',
        'kill_switch_active',
        'kill_switched_at',
        'execution_status',
    ];

    protected $casts = [
        'autonomy_level' => 'integer',
        'kill_switch_active' => 'boolean',
        'kill_switched_at' => 'datetime',
    ];
}
