<?php

declare(strict_types=1);

namespace Modules\Tlx\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DrRestoreDrill extends Model
{
    protected $table = 'tlx_dr_restore_drills';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'target_rpo_minutes' => 'integer',
        'target_rto_minutes' => 'integer',
        'actual_data_loss_minutes' => 'integer',
        'actual_recovery_time_minutes' => 'integer',
        'is_sla_compliant' => 'boolean',
        'drill_conducted_at' => 'datetime',
    ];
}
