<?php

declare(strict_types=1);

namespace Modules\Forest\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ForestTreeInventory extends Model
{
    protected $table = 'for_tree_inventories';

    protected $fillable = [
        'tree_tag',
        'plot_id',
        'species_name',
        'dbh_cm',
        'height_m',
        'form_factor_pct',
        'calculated_volume_dm3',
        'status',
    ];

    protected $casts = [
        'plot_id' => 'integer',
        'dbh_cm' => 'integer',
        'height_m' => 'integer',
        'form_factor_pct' => 'integer',
        'calculated_volume_dm3' => 'integer',
    ];

    public function plot(): BelongsTo
    {
        return $this->belongsTo(ForestConcessionPlot::class, 'plot_id');
    }
}
