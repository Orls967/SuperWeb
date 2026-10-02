<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DdTariff extends LogisticsEntity
{
    public const KINDS = ['demurrage', 'detention'];

    protected $table = 'lgx_dd_tariffs';

    protected $fillable = [
        'location_id',
        'kind',
        'size_type',
        'free_days',
        'rate_per_day_idr',
        'escalation_after_days',
        'escalated_rate_per_day_idr',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'free_days' => 'integer',
            'rate_per_day_idr' => 'integer',
            'escalation_after_days' => 'integer',
            'escalated_rate_per_day_idr' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
