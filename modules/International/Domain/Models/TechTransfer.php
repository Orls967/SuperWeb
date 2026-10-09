<?php

declare(strict_types=1);

namespace Modules\International\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechTransfer extends Model
{
    protected $table = 'intl_tech_transfers';

    protected $guarded = [];

    protected $casts = [
        'total_value_idr' => 'integer',
        'accepted_value_idr' => 'integer',
        'derivative_ip_co_owned' => 'boolean',
        'signoff_completed' => 'boolean',
    ];

    public function foreignEntity(): BelongsTo
    {
        return $this->belongsTo(ForeignEntity::class, 'foreign_entity_id');
    }
}
