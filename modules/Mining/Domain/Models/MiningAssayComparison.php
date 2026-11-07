<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningAssayComparison extends Model
{
    use HasUuids;

    protected $table = 'min_assay_comparisons';

    protected $fillable = [
        'lot_number',
        'site_id',
        'lab_assay_ni_ppm',
        'lab_assay_fe_ppm',
        'lab_assay_moisture_permil',
        'umpire_assay_ni_ppm',
        'umpire_assay_fe_ppm',
        'umpire_assay_moisture_permil',
        'bias_ni_ppm',
        'is_disputed',
        'dispute_status',
        'dispute_resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'lab_assay_ni_ppm' => 'integer',
        'lab_assay_fe_ppm' => 'integer',
        'lab_assay_moisture_permil' => 'integer',
        'umpire_assay_ni_ppm' => 'integer',
        'umpire_assay_fe_ppm' => 'integer',
        'umpire_assay_moisture_permil' => 'integer',
        'bias_ni_ppm' => 'integer',
        'is_disputed' => 'boolean',
        'dispute_resolved_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(MiningSite::class, 'site_id');
    }

    public function calculateBias(int $disputeThresholdPpm = 2000): void
    {
        $this->bias_ni_ppm = $this->lab_assay_ni_ppm - $this->umpire_assay_ni_ppm;
        if (abs($this->bias_ni_ppm) > $disputeThresholdPpm) {
            $this->is_disputed = true;
            $this->dispute_status = 'investigating';
        }
    }
}
