<?php

declare(strict_types=1);

namespace Modules\ProServices\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProServicesProjectMember extends Model
{
    protected $table = 'psv_project_members';

    protected $fillable = [
        'project_id',
        'employee_ref',
        'role_name',
        'hourly_rate_idr',
        'active',
    ];

    protected $casts = [
        'project_id' => 'integer',
        'employee_ref' => 'integer',
        'hourly_rate_idr' => 'integer',
        'active' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProServicesProject::class, 'project_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(ProServicesTimesheet::class, 'project_member_id');
    }
}
