<?php

declare(strict_types=1);

namespace Modules\Forest\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ForestTimberLog extends Model
{
    protected $table = 'for_timber_logs';

    protected $fillable = [
        'log_code',
        'plot_id',
        'permit_id',
        'tree_tag',
        'volume_dm3',
        'serial_number',
        'prev_hash',
        'canonical_hash',
        'stage',
    ];

    protected $casts = [
        'plot_id' => 'integer',
        'permit_id' => 'integer',
        'volume_dm3' => 'integer',
        'serial_number' => 'integer',
    ];

    public function permit(): BelongsTo
    {
        return $this->belongsTo(ForestHarvestPermit::class, 'permit_id');
    }

    public function plot(): BelongsTo
    {
        return $this->belongsTo(ForestConcessionPlot::class, 'plot_id');
    }
}
