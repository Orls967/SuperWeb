<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SafetyIncident extends Model
{
    protected $table = 'min_safety_incidents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'incident_code',
        'site_id',
        'area_id',
        'incident_type',
        'severity',
        'area_stopped',
        'leading_indicator_points',
        'reporter_party_id',
        'is_anonymous',
        'description',
        'occurred_at',
        'reported_at',
    ];

    protected $casts = [
        'leading_indicator_points' => 'integer',
        'is_anonymous' => 'boolean',
        'area_stopped' => 'boolean',
        'occurred_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

    public function area()
    {
        return $this->belongsTo(MiningArea::class, 'area_id');
    }
}
