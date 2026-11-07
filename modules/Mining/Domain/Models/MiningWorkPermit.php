<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mining\Domain\Enums\WorkPermitStatus;

class MiningWorkPermit extends Model
{
    use HasUuids;

    protected $table = 'min_work_permits';

    protected $fillable = [
        'id',
        'permit_number',
        'site_id',
        'pit_id',
        'worker_id',
        'permit_type',
        'activity_type',
        'valid_from',
        'valid_until',
        'supervisor_name',
        'geofence_zone',
        'risk_level',
        'status',
        'approved_by_user_id',
        'capa_ref',
    ];

    protected $casts = [
        'status' => WorkPermitStatus::class,
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->permit_type)) {
                $model->permit_type = $model->activity_type ?? 'GENERAL_HIGH_RISK';
            }
            if (empty($model->activity_type)) {
                $model->activity_type = $model->permit_type;
            }
            if (empty($model->supervisor_name)) {
                $model->supervisor_name = 'Pengawas K3 Tambang';
            }
        });
    }

    public function getValidToAttribute(): ?CarbonImmutable
    {
        return $this->valid_until ? CarbonImmutable::instance($this->valid_until) : null;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function pit(): BelongsTo
    {
        return $this->belongsTo(MiningPit::class, 'pit_id');
    }

    public function isCurrentlyValid(?CarbonImmutable $now = null): bool
    {
        $now = $now ?? CarbonImmutable::now();

        if ($this->status !== WorkPermitStatus::ACTIVE) {
            return false;
        }

        if ($this->valid_from && $now->lessThan($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->greaterThan($this->valid_until)) {
            return false;
        }

        return true;
    }
}
