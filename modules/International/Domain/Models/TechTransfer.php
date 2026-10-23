<?php

declare(strict_types=1);

namespace Modules\International\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechTransfer extends Model
{
    protected $table = 'intl_tech_transfers';

    protected $fillable = [
        'transfer_code',
        'package_title',
        'foreign_entity_id',
        'current_milestone',
        'total_value_idr',
        'accepted_value_idr',
        'derivative_ip_co_owned',
        'signoff_completed',
        'status',
    ];

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
