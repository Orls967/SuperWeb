<?php

declare(strict_types=1);

namespace Modules\Agency\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    protected $table = 'agy_lead_activities';

    protected $fillable = [
        'lead_id', 'type', 'description', 'activity_date',
    ];

    protected $casts = [
        'activity_date' => 'date',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
