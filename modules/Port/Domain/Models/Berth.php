<?php

declare(strict_types=1);

namespace Modules\Port\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Berth extends Model
{
    protected $table = 'prt_berths';

    protected $fillable = [
        'code',
        'name',
        'length_meters',
        'max_draft_meters',
        'status',
        'metadata',
    ];

    protected $casts = [
        'length_meters' => 'integer',
        'max_draft_meters' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function vesselCalls(): HasMany
    {
        return $this->hasMany(VesselCall::class, 'berth_id');
    }
}
