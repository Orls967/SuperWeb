<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log transisi append-only untuk state machine tugas gudang.
 */
class TaskEvent extends Model
{
    public $timestamps = false;

    protected $table = 'wms_task_events';

    protected $fillable = [
        'task_id',
        'from_status',
        'to_status',
        'action',
        'actor_user_id',
        'reason',
        'payload_json',
        'created_at',
    ];

    protected $casts = [
        'task_id' => 'integer',
        'actor_user_id' => 'integer',
        'payload_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}
