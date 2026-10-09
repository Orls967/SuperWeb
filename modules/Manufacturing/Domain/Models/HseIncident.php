<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Insiden & near-miss K3 (HSE): investigasi + tindakan korektif. */
class HseIncident extends Model
{
    use HasUuids;

    protected $table = 'mfg_hse_incidents';

    protected $fillable = [
        'number', 'work_center_id', 'worker_id', 'kind', 'severity', 'status',
        'title', 'description', 'investigation', 'corrective_action', 'due_date',
        'occurred_at', 'closed_at', 'reported_by_user_id',
    ];

    protected $casts = [
        'worker_id' => 'integer', 'due_date' => 'date',
        'occurred_at' => 'datetime', 'closed_at' => 'datetime',
        'reported_by_user_id' => 'integer',
    ];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }
}
