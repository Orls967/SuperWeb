<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningDispatchPlan extends Model
{
    protected $table = 'min_dispatch_plans';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'plan_uuid',
        'site_id',
        'shift_code',
        'algorithm',
        'total_cost_seconds',
        'certificate_json',
        'status',
    ];

    protected $casts = [
        'total_cost_seconds' => 'integer',
    ];

    public function assignments()
    {
        return $this->hasMany(MiningDispatchAssignment::class, 'dispatch_plan_id');
    }

    public function site()
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }
}
