<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlTestRun extends Model
{
    use HasUuids;

    protected $table = 'ef_control_test_runs';

    protected $fillable = [
        'id',
        'control_id',
        'tested_at',
        'status',
        'sample_count',
        'deviation_count',
        'details',
        'task_id',
    ];

    protected $casts = [
        'tested_at' => 'datetime',
        'sample_count' => 'integer',
        'deviation_count' => 'integer',
        'details' => 'array',
    ];

    public function controlPoint(): BelongsTo
    {
        return $this->belongsTo(ControlPoint::class, 'control_id');
    }
}
