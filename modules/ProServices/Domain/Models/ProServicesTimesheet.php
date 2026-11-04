<?php

declare(strict_types=1);

namespace Modules\ProServices\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProServicesTimesheet extends Model
{
    protected $table = 'psv_timesheets';

    protected $fillable = [
        'timesheet_code',
        'project_id',
        'project_member_id',
        'work_date',
        'hours_worked',
        'description',
        'hourly_rate_idr',
        'status',
        'approved_by_user_id',
        'approved_at',
        'is_billed',
        'is_reversal',
        'reversed_timesheet_id',
    ];

    protected $casts = [
        'project_id' => 'integer',
        'project_member_id' => 'integer',
        'hours_worked' => 'integer',
        'hourly_rate_idr' => 'integer',
        'approved_by_user_id' => 'integer',
        'approved_at' => 'datetime',
        'is_billed' => 'boolean',
        'is_reversal' => 'boolean',
        'reversed_timesheet_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProServicesProject::class, 'project_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ProServicesProjectMember::class, 'project_member_id');
    }
}
