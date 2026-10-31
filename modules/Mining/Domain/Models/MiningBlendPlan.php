<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MiningBlendPlan extends Model
{
    protected $table = 'min_blend_plans';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'plan_uuid',
        'site_id',
        'target_vessel_name',
        'target_min_kg',
        'target_max_kg',
        'target_specs_json',
        'allocated_kg',
        'allocations_json',
        'certificate_json',
        'status',
    ];

    protected $casts = [
        'target_min_kg' => 'integer',
        'target_max_kg' => 'integer',
        'allocated_kg' => 'integer',
    ];

    public function site()
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }
}
