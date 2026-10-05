<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu inspeksi: reading, hasil lulus/gagal/waived. */
class Inspection extends Model
{
    use HasUuids;

    protected $table = 'mfg_inspections';

    protected $fillable = [
        'plan_id', 'gauge_id', 'stage', 'subject_type', 'subject_id', 'lot_id',
        'result', 'readings', 'findings', 'approval_id', 'inspected_by_user_id', 'decided_at',
    ];

    protected $casts = [
        'gauge_id' => 'integer', 'readings' => 'array', 'approval_id' => 'integer', 'inspected_by_user_id' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InspectionPlan::class, 'plan_id');
    }

    public function ncrs(): HasMany
    {
        return $this->hasMany(Ncr::class, 'inspection_id');
    }
}
