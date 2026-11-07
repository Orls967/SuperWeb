<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Mining\Domain\Enums\ReconciliationRunStatus;

class MiningReconciliationRun extends Model
{
    use HasUuids;

    protected $table = 'min_reconciliation_runs';

    protected $fillable = [
        'id',
        'run_uuid',
        'run_number',
        'site_id',
        'period_month',
        'period_start',
        'period_end',
        'status',
        'total_ore_mined_tons',
        'total_stockpile_tons',
        'total_shipped_tons',
        'total_weighed_kg',
        'total_stockpile_in_kg',
        'total_stockpile_out_kg',
        'total_shipped_kg',
        'variance_tons',
        'variance_kg',
        'variance_bps',
        'investigation_notes',
        'approved_by_user_id',
        'approved_at',
        'locked_at',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'total_ore_mined_tons' => 'integer',
        'total_stockpile_tons' => 'integer',
        'total_shipped_tons' => 'integer',
        'variance_tons' => 'integer',
        'variance_bps' => 'integer',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function statusEnum(): ReconciliationRunStatus
    {
        if ($this->status instanceof ReconciliationRunStatus) {
            return $this->status;
        }

        $val = strtolower((string) ($this->attributes['status'] ?? 'draft'));

        return ReconciliationRunStatus::tryFrom($val) ?? ReconciliationRunStatus::DRAFT;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->run_uuid)) {
                $model->run_uuid = $model->run_number ?? (string) Str::uuid();
            }
            if (empty($model->run_number)) {
                $model->run_number = $model->run_uuid;
            }
            if (empty($model->period_start)) {
                $model->period_start = now()->startOfMonth();
            }
            if (empty($model->period_end)) {
                $model->period_end = now()->endOfMonth();
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }
}
