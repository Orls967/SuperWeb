<?php

declare(strict_types=1);

namespace Modules\Forest\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ForestConcessionPlot extends Model
{
    protected $table = 'for_concession_areas';

    protected $fillable = [
        'plot_code',
        'concession_name',
        'area_hectares',
        'annual_quota_m3',
        'harvested_volume_m3',
        'status',
    ];

    protected $casts = [
        'area_hectares' => 'integer',
        'annual_quota_m3' => 'integer',
        'harvested_volume_m3' => 'integer',
    ];

    public function treeInventories(): HasMany
    {
        return $this->hasMany(ForestTreeInventory::class, 'plot_id');
    }

    public function harvestPermits(): HasMany
    {
        return $this->hasMany(ForestHarvestPermit::class, 'plot_id');
    }
}
