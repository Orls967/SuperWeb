<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationMaintenanceTransition extends Model
{
    public $timestamps = false;

    protected $table = 'avi_maintenance_transitions';

    protected $fillable = [
        'maintenance_visit_id',
        'from_status',
        'to_status',
        'trigger',
        'actor_id',
        'notes',
        'created_at',
    ];

    public function maintenanceVisit(): BelongsTo
    {
        return $this->belongsTo(AviationMaintenanceVisit::class, 'maintenance_visit_id');
    }
}
