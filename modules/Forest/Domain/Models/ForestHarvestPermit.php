<?php

declare(strict_types=1);

namespace Modules\Forest\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ForestHarvestPermit extends Model
{
    protected $table = 'for_harvest_permits';

    protected $fillable = [
        'permit_code',
        'plot_id',
        'authorized_volume_m3',
        'harvested_volume_m3',
        'valid_from',
        'valid_until',
        'status',
        'requester_user_ref',
        'approver_user_ref',
        'approved_at',
    ];

    protected $casts = [
        'plot_id' => 'integer',
        'authorized_volume_m3' => 'integer',
        'harvested_volume_m3' => 'integer',
        'requester_user_ref' => 'integer',
        'approver_user_ref' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'approved_at' => 'datetime',
    ];

    public function plot(): BelongsTo
    {
        return $this->belongsTo(ForestConcessionPlot::class, 'plot_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ForestTimberLog::class, 'permit_id');
    }
}
