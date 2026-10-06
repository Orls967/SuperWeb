<?php

declare(strict_types=1);

namespace Modules\Plm\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeOrder extends Model
{
    use HasUuids;

    protected $table = 'plm_change_orders';

    protected $fillable = [
        'eco_number',
        'ebom_id',
        'title',
        'reason',
        'cost_impact_idr',
        'disposition',
        'prev_hash',
        'hash',
        'status',
    ];

    protected $casts = [
        'cost_impact_idr' => 'integer',
    ];

    public function ebom(): BelongsTo
    {
        return $this->belongsTo(EngineeringBom::class, 'ebom_id');
    }
}
