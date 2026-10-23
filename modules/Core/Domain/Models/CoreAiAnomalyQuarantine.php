<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CoreAiAnomalyQuarantine extends Model
{
    protected $table = 'core_ai_anomaly_quarantines';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'quarantine_code',
        'transaction_ref',
        'customer_ref',
        'amount_idr',
        'risk_score',
        'anomaly_reasons',
        'decision_snapshot_id',
        'status',
        'quarantined_at',
        'released_at',
        'released_by',
        'release_justification',
    ];

    protected $casts = [
        'amount_idr' => 'integer',
        'risk_score' => 'float',
        'anomaly_reasons' => 'array',
        'quarantined_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->quarantine_code)) {
                $model->quarantine_code = 'QRN-'.Str::upper(Str::random(8));
            }
            if (empty($model->quarantined_at)) {
                $model->quarantined_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<CoreAiDecisionSnapshot, self>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CoreAiDecisionSnapshot::class, 'decision_snapshot_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveQuarantine(Builder $query): Builder
    {
        return $query->where('status', 'QUARANTINED');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->where('risk_score', '>=', 0.80);
    }
}
