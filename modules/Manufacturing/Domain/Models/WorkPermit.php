<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Izin kerja berisiko (hot work / confined space) dengan approval + masa berlaku. */
class WorkPermit extends Model
{
    use HasUuids;

    protected $table = 'mfg_work_permits';

    protected $fillable = [
        'number', 'work_center_id', 'permit_type', 'status', 'hazards', 'controls',
        'valid_from', 'valid_until', 'approval_id', 'requested_by_user_id', 'approved_at',
    ];

    protected $casts = [
        'valid_from' => 'datetime', 'valid_until' => 'datetime',
        'approval_id' => 'integer', 'requested_by_user_id' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    /** Izin masih berlaku untuk pekerjaan saat ini. */
    public function isActiveAt(?\DateTimeInterface $at = null): bool
    {
        $now = $at ?? now();

        return $this->status === 'active'
            && $this->valid_from <= $now
            && $this->valid_until >= $now;
    }
}
