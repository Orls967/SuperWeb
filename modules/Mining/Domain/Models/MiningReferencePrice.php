<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MiningReferencePrice extends Model
{
    use HasUuids;

    protected $table = 'min_reference_prices';

    protected $fillable = [
        'commodity',
        'price_type',
        'period_date',
        'period_phase',
        'price_cents',
        'currency',
        'source',
        'published_at',
    ];

    protected $casts = [
        'period_phase' => 'integer',
        'price_cents' => 'integer',
        'period_date' => 'date',
        'published_at' => 'datetime',
    ];
}
