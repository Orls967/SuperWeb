<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model riwayat mutasi status dan audit trail tugas kotak masuk.
 */
class CoreTaskHistory extends Model
{
    protected $table = 'core_task_histories';

    protected $fillable = [
        'task_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'notes',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(CoreTask::class, 'task_id', 'id');
    }
}
