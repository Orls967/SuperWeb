<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SybAgriMilestone extends Model
{
    protected $table = 'syb_agri_milestones';

    protected $fillable = [
        'milestone_code',
        'facility_code',
        'milestone_number',
        'title',
        'target_ndvi_bps',
        'disbursement_percentage',
        'amount_idr',
        'verified_at',
        'status',
    ];

    protected $casts = [
        'milestone_number' => 'integer',
        'target_ndvi_bps' => 'integer',
        'disbursement_percentage' => 'integer',
        'amount_idr' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(SybAgriMicroFacility::class, 'facility_code', 'facility_code');
    }
}

