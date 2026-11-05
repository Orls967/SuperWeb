<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SybMicroRestructuring extends Model
{
    protected $table = 'syb_micro_restructurings';

    protected $fillable = [
        'restruct_code',
        'facility_code',
        'reason',
        'loss_type',
        'previous_nisbah_bps',
        'new_nisbah_bps',
        'previous_tenor_months',
        'new_tenor_months',
        'restructured_principal_idr',
        'moral_hazard_detected',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'previous_nisbah_bps' => 'integer',
        'new_nisbah_bps' => 'integer',
        'previous_tenor_months' => 'integer',
        'new_tenor_months' => 'integer',
        'restructured_principal_idr' => 'integer',
        'moral_hazard_detected' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(SybMicroFacility::class, 'facility_code', 'facility_code');
    }
}
