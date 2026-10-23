<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Models\Gig;

use Illuminate\Database\Eloquent\Model;

class UniversalGigTask extends Model
{
    protected $table = 'hcm_universal_gig_tasks';

    protected $fillable = [
        'task_code',
        'source_domain', // RESTO, VENUE, LOGISTICS, FLEET, HOSPITAL, HOTEL, MINING
        'title',
        'description',
        'required_k3_certificate',
        'hourly_rate_idr',
        'estimated_hours',
        'total_compensation_idr',
        'assigned_user_id',
        'supervisor_user_id',
        'status', // OPEN, CLAIMED, IN_PROGRESS, COMPLETED, PAID, CANCELLED
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'hourly_rate_idr' => 'integer',
        'estimated_hours' => 'integer',
        'total_compensation_idr' => 'integer',
        'assigned_user_id' => 'integer',
        'supervisor_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
