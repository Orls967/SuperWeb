<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAuditRun extends Model
{
    protected $table = 'plt_audit_runs';

    protected $fillable = [
        'run_uuid',
        'source_a_status',
        'source_b_status',
        'discrepancy_count',
        'findings',
        'completed_at',
    ];

    protected $casts = [
        'findings' => 'array',
        'completed_at' => 'datetime',
    ];
}
