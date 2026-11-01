<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class MiningWorkPermit extends Model
{
    protected $table = 'min_work_permits';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'site_id',
        'area_id',
        'permit_number',
        'permit_type',
        'supervisor_name',
        'worker_party_id',
        'allowed_pit_id',
        'allowed_latitude',
        'allowed_longitude',
        'allowed_radius_meters',
        'valid_from',
        'valid_until',
        'approval_status',
        'status',
    ];

    protected $casts = [
        'allowed_latitude' => 'float',
        'allowed_longitude' => 'float',
        'allowed_radius_meters' => 'float',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->supervisor_name)) {
                $model->supervisor_name = 'HSE Superintendent';
            }
        });
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && Carbon::now()->greaterThan($this->valid_until);
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ($this->approval_status === 'APPROVED' || $this->status === 'ACTIVE');
    }

    public function area()
    {
        return $this->belongsTo(MiningArea::class, 'area_id');
    }
}
