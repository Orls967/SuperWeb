<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CoreMobileSyncEvent extends Model
{
    protected $table = 'core_mobile_sync_events';

    protected $fillable = [
        'idempotency_key',
        'device_uuid',
        'role_scope',
        'event_type',
        'payload',
        'occurred_offline_at',
        'server_received_at',
        'conflict_status',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_offline_at' => 'datetime',
            'server_received_at' => 'datetime',
        ];
    }
}
