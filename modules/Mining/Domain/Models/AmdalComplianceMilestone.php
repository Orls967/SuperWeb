<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AmdalComplianceMilestone extends Model
{
    protected $table = 'min_amdal_compliance_milestones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'is_fully_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];
}
