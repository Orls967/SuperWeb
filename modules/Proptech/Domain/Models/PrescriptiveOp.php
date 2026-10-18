<?php

declare(strict_types=1);

namespace Modules\Proptech\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptiveOp extends Model
{
    protected $table = 'prp_prescriptive_ops';

    protected $fillable = [
        'zone_id',
        'action_code',
        'recommendation_text',
        'potential_kwh_saved',
        'potential_saving_idr',
        'payback_months',
        'status',
    ];

    protected $casts = [
        'potential_kwh_saved' => 'decimal:2',
        'potential_saving_idr' => 'integer',
        'payback_months' => 'integer',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BuildingZone::class, 'zone_id');
    }
}
