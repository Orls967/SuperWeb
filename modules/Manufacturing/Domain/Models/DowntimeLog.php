<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Downtime per work center: kode alasan standar (bahan OEE Fase 40). */
class DowntimeLog extends Model
{
    protected $table = 'mfg_downtime_logs';

    protected $fillable = [
        'work_center_id', 'production_order_id', 'reason_code', 'detail',
        'started_at', 'ended_at', 'minutes', 'created_by_user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime', 'ended_at' => 'datetime',
        'minutes' => 'integer', 'created_by_user_id' => 'integer',
    ];

    public const REASON_CODES = ['machine_down', 'material_wait', 'setup', 'break', 'other'];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }
}
