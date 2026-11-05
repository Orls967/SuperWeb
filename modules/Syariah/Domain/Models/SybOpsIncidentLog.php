<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class SybOpsIncidentLog extends Model
{
    protected $table = 'syb_ops_incident_logs';

    protected $fillable = [
        'incident_ref',
        'severity',
        'category',
        'description',
        'entity_reference',
        'resolution_status',
        'resolution_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
