<?php

namespace Modules\Hospital\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResult extends Model
{
    protected $table = 'hsp_lab_results';

    protected $fillable = [
        'result_code',
        'specimen_id',
        'test_code',
        'numeric_value',
        'text_value',
        'is_critical',
        'verified_by_pathologist_hash',
        'status',
    ];

    protected $casts = [
        'is_critical' => 'boolean',
    ];

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(LabSpecimen::class, 'specimen_id');
    }
}
