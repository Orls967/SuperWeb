<?php

declare(strict_types=1);

namespace Modules\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AccessAuditLog extends Model
{
    protected $table = 'sec_access_audit_logs';

    protected $fillable = [
        'caller_service',
        'callee_service',
        'action',
        'status',
        'ability_used',
        'source_ip',
        'context',
        'accessed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'accessed_at' => 'datetime',
    ];
}
